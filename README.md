# Database Dumper Script

A PHP script to automate the process of exporting a database from a remote server and downloading it locally. It supports multiple projects and environments, allowing you to easily switch between them using CLI arguments or an interactive prompt.

---


> [!IMPORTANT]
> 
> **REQUIREMENTS**
>  - **PHP**: ^8.1
>  - **Dependencies**:
>        - `phpseclib/phpseclib` ^3.0
> 
> **ASSUMPTIONS**
> - This script assumes that `pg_dump` is being used on the server.


## Installation 
1. Clone the repository:
```bash
  git clone git@github.com:PG-Momik/DB-Dumper.git
  cd db-dumper
```
2. Install dependencies:
```bash
  composer install
```
3. Define environment configurations in `env.json`. (Use `env.example.json` as a reference).
4. `env.json` will be validated against `schema.json` automatically when executing the script.


## Usage

### Using CLI Arguments
You can specify the project and environment directly via CLI flags.

```
  php index.php -p "My project name" -e "develop"
```

### Interactive Mode

Run the script without arguments to use the interactive mode. You'll be prompted to select a project and environment.

```
  php index.php
```


## Script Workflow

1. Validation: Validates env.json against schema.json using EnvValidator.
2. Argument Extraction: Extracts CLI arguments (-p for project, -e for environment) or enters interactive mode.
3. Database Dump: Initiates the database dump on the specified server using DatabaseDumper class.
4. Completion: Downloads the dump file locally and provides details.

## Example Output
### CLI Arguments Mode
![Screenshot from 2024-12-04 10-50-16.png](screenshots/Screenshot%20from%202024-12-04%2010-50-16.png)

## Interactive Mode
![Screenshot from 2024-12-04 10-51-01.png](screenshots/Screenshot%20from%202024-12-04%2010-51-01.png)

## File Structure
```
db-dumper/
│
├── classes/
│   ├── DatabaseDumper.php    # Handles the database dump process
│   ├── EnvValidator.php      # Validates env.json against schema.json
│
├── index.php                 # Main script
├── composer.json             # Composer configuration
├── env.json                  # User-defined configuration file
├── env.example.json          # env.json example
├── schema.json               # Defines structure of env.json
└── README.md                 # Documentation
```