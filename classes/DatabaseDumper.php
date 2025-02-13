<?php

declare(strict_types=1);

namespace classes;

use Exception;
use phpseclib3\Crypt\Common\AsymmetricKey;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class DatabaseDumper
{
    private AsymmetricKey $privateKey;
    private array $projectConfig;
    private string $environmentName;
    private array $environmentConfig;
    private string $serverIp;
    private string $serverUser;

    /**
     * @throws Exception
     */
    public function __construct(array $envJson, string $projectName, string $environment)
    {
        $this->environmentName = $environment;
        $this->projectConfig   = $this->findProjectConfig($envJson, $projectName);

        $this->validatePrivateKey($envJson);
    }

    /**
     * @throws Exception
     */
    private function findProjectConfig(array $envJson, string $projectName)
    {
        $filteredProjects = array_filter($envJson['projects'], fn ($project) => $project['name'] === $projectName);

        if (empty($filteredProjects)) {
            throw new Exception("Project not found: $projectName");
        }

        return $filteredProjects[0]['config'];
    }

    /**
     * @throws Exception
     */
    private function validatePrivateKey(array $envJson): void
    {
        if (!isset($envJson['private_key_path'])) {
            throw new Exception("Private key path is not set in the env.json file.");
        }

        $privateKeyPath = str_replace('~', getenv('HOME'), $envJson['private_key_path']);

        if (!file_exists($privateKeyPath)) {
            throw new Exception("Private key file does not exist at the specified path: $privateKeyPath");
        }

        $this->environmentConfig = $this->getEnvironmentDetails();
        $this->serverIp          = $this->getServerIpFromConfig();
        $this->serverUser        = $this->getServerUserFromConfig();
        $this->privateKey        = PublicKeyLoader::load(file_get_contents($privateKeyPath));
    }

    /**
     * @return string
     */
    private function getServerUserFromConfig(): string
    {
        return $this->environmentConfig['server_user'];
    }

    /**
     * @return string
     */
    private function getServerIpFromConfig(): string
    {
        return $this->environmentConfig['server_ip'];
    }

    /**
     * @return string
     */
    private function getDbConnectionString(): string
    {
        return $this->environmentConfig['db_connection_string'];
    }

    /**
     * @return string
     */
    private function getDumpFilename(): string
    {
        return $this->environmentConfig['dump_filename'];
    }

    /**
     * @param string $dbConnectionString
     *
     * @return array
     */
    private function extractDbStringParams(string $dbConnectionString): array
    {
        $connectionString = str_replace('postgresql://', '', $dbConnectionString);

        list($credentials, $hostInfo) = explode('@', $connectionString);
        list($dbUser, $dbPassword)    = explode(':', $credentials);

        $exploded = explode(':', $hostInfo);
        $dbHost = $exploded[0];

        $exploded = explode('/', $exploded[1]);
        $dbPort = $exploded[0];

        $exploded = explode('?', $exploded[1]);
        $dbName = $exploded[0];

        return [$dbPassword, $dbUser, $dbHost, $dbPort, $dbName];
    }

    /**
     * @throws Exception
     */
    private function createSshConnection(): SSH2
    {
        $ssh = new SSH2($this->serverIp);

        if (!$ssh->login($this->serverUser, $this->privateKey)) {
            throw new Exception("Key-based authentication failed.");
        }

        return $ssh;
    }

    /**
     * @throws Exception
     */
    public function performDatabaseDump(): array
    {
        try {
            echo PHP_EOL."=================Started taking DB dump======================".PHP_EOL.PHP_EOL;

            $ssh = $this->createSshConnection();

            $exportFilename     = $this->getDumpFilename();
            $dbConnectionString = $this->getDbConnectionString();

            list($dbPassword, $dbUser, $dbHost, $dbPort, $dbName) = $this->extractDbStringParams($dbConnectionString);

            $dumpCommand = sprintf("PGPASSWORD=%s pg_dump -U %s -h %s -p %d -d %s > %s", $dbPassword, $dbUser, $dbHost, $dbPort, $dbName, $exportFilename);

            $output = $ssh->exec($dumpCommand);
            $exitStatus = $ssh->getExitStatus();

            if ($exitStatus) {
                throw new Exception("Dump command failed with exit status $exitStatus");
            }

            $ssh->disconnect();
            $ssh = $this->createSshConnection();

            $fileCheckCommand = sprintf("[ -s '%s' ] && echo 'Dumped file exists.' || echo 'Dump file is empty'", $exportFilename);
            $fileCheckOutput = $ssh->exec($fileCheckCommand);

            return [
                'dump_status' => true,
                'export_filename' => $exportFilename,
                'file_check_output' => trim($fileCheckOutput)
            ];

        } catch (Exception $e) {
            throw new Exception("Database dump failed: " . $e->getMessage());
        }
    }

    /**
     * @return array
     */
    private function getEnvironmentDetails(): array
    {
        $filtered = array_values(array_filter($this->projectConfig['environments'], fn ($environment) => $environment['name'] === $this->environmentName));

        return $filtered[0];
    }
}



