# Core Commands

## ContentType

### Activate
> Activate a content type

* **--all** : Make all contenttypes: [ticket]
* **--deactivate** : Deactivate contenttypes
* **--force** : Activate the contenttypes even if the mapping is not up to date (flagged as draft)

### Clean
> Clean all deleted content types

```bash
php bin/console ems:contenttype:clean
```

### Delete
> Delete all instances of a content type

```bash
php bin/console ems:contenttype:delete <name>
```

### Export
>Export a search result of a content type to a specific format

```bash
php bin/console ems:contenttype:export [options] [--] <contentTypeName> [<format> [<query> [<outputFile>]]]
```

* **--environment=ENVIRONMENT** : The environment to use for the query, it will use the default environment if not defined
* **--withBusinessId** : Replace internal OUUIDs by business values
* **--scrollSize=SCROLLSIZE** : Size of the elasticsearch scroll request [default: 100]
* **--scrollTimeout=SCROLLTIMEOUT** : Time to migrate "scrollSize" items i.e. 30s or 2m [default: "1m"]
* **--baseUrl=BASEURL** : Base url of the application (in order to generate a link)

### Import
>Import json files from a zip file as content type's documents

```bash
php bin/console ems:contenttype:import [options] [--] <contentTypeName> <archive>
```

* **--bulkSize[=BULKSIZE]** : Size of the elasticsearch bulk request [default: 500]
* **--raw** : The content will be imported as is. Without any field validation, data stripping or field protection
* **--dont-sign-data** : The content will not be signed during the import process
* **--force** : Also treat document in draft mode
* **--dont-finalize** : Don't finalize document
* **--businessKey** : Try to identify documents by their business keys

### Lock
> Lock a content type

```bash
php bin/console ems:contenttype:lock [options] [--] <contentType> <time>
```

* **--query[=QUERY]** : ES query [default: "{}"]
* **--user=USER** : lock username [default: "EMS_COMMAND"]
* **--force** : do not check for already locked revisions
* **--if-empty** : lock if there are no pending locks for the same user
* **--ouuid[=OUUID]** : lock a specific ouuid

### Migrate
>Migrate a content type from an elasticsearch index

```bash
php bin/console ems:contenttype:migrate [options] [--] <elasticsearchIndex> <contentTypeNameFrom> [<contentTypeNameTo> [<scrollSize> [<scrollTimeout>]]]
```

* **--bulkSize[=BULKSIZE]** : Size of the elasticsearch bulk request [default: "500"]
* **--force** : Allow to import from the default environment and to draft revision
* **--raw** : The content will be imported as is. Without any field validation, data stripping or field protection
* **--sign-data** : The content will be (re)signed during the reindexing process
* **--searchQuery[=SEARCHQUERY]** : Query used to find elasticsearch records to import [default: "{\"sort\":{\"_uid\":{\"order\":\"asc\"}}}"]
* **--dont-finalize** : Don't finalize document

### Recompute
>Recompute a content type

```bash
php bin/console ems:contenttype:recompute [options] [--] <contentType>
```

* **--force** : do not check for already locked revisions
* **--missing** : will recompute the objects that are missing in their default environment only
* **--continue** : continue a recompute
* **--no-align** : don't keep the revisions aligned to all already aligned environments
* **--cron** : optimized for automated recurring recompute calls, tries --continue, when no locks are found for user runs command without --continue
* **--ouuid[=OUUID]** : recompute a specific revision ouuid
* **--deep** : deep recompute form will be submitted and transformers triggered

## Notification

### Send
> Send all notifications and notification's responses emails
* **--dry-run** 

```bash
php bin/console ems:notification:send --dry-run
```

### Bulk-action
> Bulk all notifications actions for the passed query

* **actionId** : notification id ems content type notification action
* **query** : json escaped elasticsearch query
* **--username** : this username will be the created by on the notification, default is ems
* **--environment** : environment for executing the passed query, default is the notification contentType environment 
* **--force** : will only create notifications if force is true

```bash
php bin/console ems:notification:bulk-action 72 {\"query\":{\"bool\":{\"must\":[{\"range\":{\"expiration_date\":{\"gte\":\"now\",\"lte\":\"now+1M\"}}}]}}} --force --username="bulker" --environment=live
```