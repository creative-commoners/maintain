# SilverStripe Maintain

A command line tool to help with maintenance across the SilverStripe supported module suite.

## Installation

```
composer global require silverstripe/maintain 1.x-dev
```

## Usage

Ensure Composer's global bin is in your system path if you want to reference it as `maintain` only.

```
php bin/maintain github:labels
```

## Configuration

* Define your GitHub personal access token in a `GITHUB_ACCESS_TOKEN` environment variable
* Ensure you have Git configured locally and have permission to push to each repository
* Your GitHub access token will need permission to write labels to repositories, otherwise the command
  will skip repositories without sufficient permission

## Templates

The templates for files and data that are synchronised between all supportd modules belong in the
`templates` directory. These files serve as the source of truth for all sync commands.

The `labels.json` file contains the configuration for SilverStripe repository GitHub labels, but
all other files will be copied into each repository verbatim.

## Notes

### GitHub rate limiting

The GitHub rate limit is 5000 requests per hour. By default this library performs 36 requests per module
(99 at time of writing) = 3,564 requests. Note that if you run this script more than one in an hour you're
likely to hit the GitHub rate limit and will start seeing failures. There is currently no "bulk update" API
for labels, so this is something to be aware of.

You can run `php bin/maintain github:rate-limit` to check the current status.
