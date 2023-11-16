# Drupal AtoM Importer

This module creates Nodes and Taxonomies based on JSON data read from an [AtoM API](https://www.accesstomemory.org/en/docs/latest/dev-manual/api/api-intro/).

Taxonomy terms are created for shared fields like `repository` and `creators`. Other fields are indexed as a Node entity.

## Configuration

Navigate to `/admin/config/atom`. You must configure your AtoM Host and REST API key. You do not need to suffix the `/index.php/api` part of the URL.

The Repo ID(s) field is optional. If you leave this blank, all items from all repos will be synced to Drupal. You can separate multiple repo IDs with a comma.

## Cron job

The module creates a Cron job which will run the synchronization at the specified interval. We highly recommend installing the Ultimate Cron module, so you can set this job to run on, for instance, a daily basis, while allowing other tasks to run more frequently.

## Manual synchronization

Click the Download button on the configuration page to immediately run the synchronization.

## Example

You can see an example of the module in use on U of T's Media Commons Archives [Drupal website](https://media-archives.library.utoronto.ca/archival-collections) - the module enables an additional discovery point for the Media Commons Archives [AtoM archival descriptions](https://discoverarchives.library.utoronto.ca/index.php/university-of-toronto-media-commons).
