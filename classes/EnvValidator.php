<?php

declare(strict_types=1);

namespace classes;

use Exception;

class EnvValidator
{
    private array $schemaJson;

    public function __construct(private readonly string $envJsonPath, private readonly string $schemaJsonPath)
    {
    }

    /**
     * @return bool
     */
    public function validate(): bool
    {
        if (!$this->basicJsonValidation()) {
            return false;
        }

        $this->schemaJson = json_decode(file_get_contents($this->schemaJsonPath), true);
        $envJson = json_decode(file_get_contents($this->envJsonPath), true);

        return $this->validateFields($this->schemaJson['required'], $envJson)
            && $this->validateProjects($this->schemaJson['projects'], $envJson['projects']);
    }

    /**
     * @param array $required
     * @param array $data
     *
     * @return bool
     */
    private function validateFields(array $required, array $data): bool
    {
        foreach ($required as $field => $type) {
            if (!isset($data[$field])) {
                echo "Missing required field: $field\n";
                return false;
            }

            if (!$this->validateType($data[$field], $type)) {
                echo "Field '$field' must be of type $type.\n";
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $projectSchema
     * @param array $projects
     *
     * @return bool
     */
    private function validateProjects(array $projectSchema, array $projects): bool
    {
        foreach ($projects as $project) {
            if (!$this->validateFields($projectSchema, $project)) {
                return false;
            }

            if (!isset($project['config']) || !$this->validateConfig($this->schemaJson['config'], $project['config'])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $configSchema
     * @param array $config
     *
     * @return bool
     */
    private function validateConfig(array $configSchema, array $config): bool
    {
        if (!isset($config['environments']) || !is_array($config['environments'])) {
            echo "Config must contain 'environments' as an array.\n";
            return false;
        }

        foreach ($config['environments'] as $environment) {
            if (!$this->validateFields($this->schemaJson['environments'], $environment)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return bool
     */
    private function basicJsonValidation(): bool
    {
        return $this->jsonFileExists($this->envJsonPath)
            && $this->isValidJson($this->envJsonPath)
            && $this->jsonFileExists($this->schemaJsonPath)
            && $this->isValidJson($this->schemaJsonPath);
    }

    /**
     * @param mixed $value
     * @param string $type
     *
     * @return bool
     */
    private function validateType(mixed $value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_array($value) && $this->isAssociativeArray($value);
            default:
                echo "Unknown type '$type'.\n";
                return false;
        }
    }

    /**
     * @param array $array
     *
     * @return bool
     */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param string $jsonFilePath
     *
     * @return bool
     */
    private function jsonFileExists(string $jsonFilePath): bool
    {
        if (!file_exists($jsonFilePath)) {
            echo "JSON file not found: $jsonFilePath\n";
            return false;
        }
        return true;
    }

    /**
     * @param string $jsonFilePath
     *
     * @return bool
     */
    private function isValidJson(string $jsonFilePath): bool
    {
        try {
            json_decode(file_get_contents($jsonFilePath), true, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (Exception) {
            echo "Invalid JSON format in file: $jsonFilePath\n";
            return false;
        }
    }
}
