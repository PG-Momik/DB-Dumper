<?php

use classes\DatabaseDumper;
use classes\EnvValidator;

require 'vendor/autoload.php';

/**
 * Handle 'p' and 'e' flags from cli.
 *
 * @return array{project: ?string, environment: ?string}
 */
function extractCliArguments(): array {
    $options = getopt('p:e:', ['project:', 'environment:']);
    return [
        'project'     => $options['p'] ?? $options['project'] ?? null,
        'environment' => $options['e'] ?? $options['environment'] ?? null
    ];
}


/**
 * Check if arguments passed via cli.
 *
 * @param array $cliArgs
 * @return bool
 */
function cliArgumentsSet(array $cliArgs): bool
{
    return $cliArgs['project'] && $cliArgs['environment'];
}


/**
 * Start interactive prompts to get project and environment via cli.
 *
 * @param array $envJson
 *
 * @return array|null
 */
function selectProjectInteractively(array $envJson): ?array {
    if (empty($envJson['projects'])) {
        echo "No projects found in env.json\n";

        return null;
    }

    /** Display the Projects table */

    echo "+---------------------+------------------+\n";
    echo "| Project Name        | Selection Key    |\n";
    echo "+---------------------+------------------+\n";

    $projectChoices = [];

    foreach ($envJson['projects'] as $index => $project) {
        $projectName  = $project['name'];
        $environments = array_map(fn($environment) => $environment['name'],$project['config']['environments']);

        printf("| %-19s | %-16s |\n", $projectName, $index);

        $projectChoices[$index] = [
            'name'         => $projectName,
            'environments' => $environments
        ];
    }

    echo "+---------------------+------------------+\n";

    echo "Select a project (Enter the selection key): ";
    $projectInput = trim(fgets(STDIN));

    if (!isset($projectChoices[$projectInput])) {
        echo "Invalid project selection.\n";

        return null;
    }

    $selectedProject = $projectChoices[$projectInput];

    /** End the Projects table */


    /** Display the Environments table */
    if (count($selectedProject['environments']) > 1) {
        echo "\n+---------------------+------------------+\n";
        echo "| Environment         | Selection Key    |\n";
        echo "+---------------------+------------------+\n";

        foreach ($selectedProject['environments'] as $index => $envName) {
            printf("| %-19s | %-16s |\n", $envName, $index);
        }

        echo "+---------------------+------------------+\n";

        echo "Select an environment for {$selectedProject['name']} (Enter the selection key): ";
        $envInput = trim(fgets(STDIN));

        if (!isset($selectedProject['environments'][$envInput])) {
            echo "Invalid environment selection.\n";

            return null;
        }

        $environment = $selectedProject['environments'][$envInput];
    } else {
        $environment = $selectedProject['environments'][0];
    }

    /** End the Environments table */

    return [
        'project'     => $selectedProject['name'],
        'environment' => $environment
    ];
}



try {
    $envJsonPath    = 'env.json';
    $schemaJsonPath = 'schema.json';
    $envValidator   = new EnvValidator($envJsonPath, $schemaJsonPath);

    if(!$envValidator->validate()) {
        die();
    }

    $envJson = json_decode(file_get_contents($envJsonPath), true);
    $cliArgs = extractCliArguments();

    if (cliArgumentsSet($cliArgs)) {
        $projectName = $cliArgs['project'];
        $environment = $cliArgs['environment'];
    } else {
        $interactiveSelection = selectProjectInteractively($envJson);

        if (!$interactiveSelection) {
            die("Could not determine project and environment.\n");
        }

        $projectName = $interactiveSelection['project'];
        $environment = $interactiveSelection['environment'];
    }

    $dumper = new DatabaseDumper($envJson, $projectName, $environment);
    $result = $dumper->performDatabaseDump();

    echo "Database dump completed.\n";
    echo "Server response: " . $result['file_check_output'] . "\n";
    echo "Dump file: " . $result['export_filename'] . "\n";

    $localDumpPath = rtrim(getenv('HOME'), '/') . '/Downloads/' . basename($result['export_filename']);

    $dumper->downloadDumpFile($localDumpPath);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
