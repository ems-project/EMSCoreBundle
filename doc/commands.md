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

### Transform
> Apply defined field transformers in the migration mapping.
> More information about [contentType transformers](../master/doc/ContentTypes/transformers.md).

```bash
Usage:
  emsco:contenttype:transform [options] [--] <contentType>

Arguments:
  contentType                        ContentType name

Options:
      --scroll-size=SCROLLSIZE        Size of the elasticsearch scroll request
      --scroll-timeout=SCROLLTIMEOUT  Time to migrate "scrollSize" items i.e. 30s or 2m
      --search-query[=SEARCHQUERY]    Query used to find elasticsearch records to transform [default: "{}"]
      --dry-run                       Dry run
      --user=USER                     Lock user [default: "SYSTEM_CONTENT_TRANSFORM"]
```

* **--scroll-size**: Size of the elasticsearch scroll request
* **--scroll-timeout**: Time to migrate "scrollSize" items i.e. 30s or 2m
* **--search-query**: json escaped string with es query
* **--dry-run** : will not commit the database transactions

## Environment

### Align

```bash
Usage:
  emsco:environment:align [options] [--] <source> <target>

Arguments:
  source                               Environment source name
  target                               Environment target name

Options:
      --scroll-size=SCROLL-SIZE        Size of the elasticsearch scroll request
      --scroll-timeout=SCROLL-TIMEOUT  Time to migrate "scrollSize" items i.e. 30s or 2m
      --search-query[=SEARCH-QUERY]    Query used to find elasticsearch records to import [default: "{}"]
      --force                          If set, the task will be performed (protection)
      --snapshot                       If set, the target environment will be tagged as a snapshot after the alignment
      --user=USER                      Lock user [default: "SYSTEM_ALIGN"]
```

## Revision

### Task create
> Create revision task based on ES query

The command will not create tasks:
* if tasks are not enabled (see [tasks documentation](elasticms.md#document-tasks))
* if the revision has a current task or planned tasks

```bash
Usage:
  emsco:revision:task:create [options] [--] <environment>

Arguments:
  environment

Options:
      --task=TASK                    {\"title\":\"title\",\"assignee\":\"username\",\"description\":\"optional\"}
      --field-assignee=FIELDASSIGNEE  assignee field in es document
      --field-deadline=FIELDDEADLINE  deadline field in es document
      --default-owner=DEFAULTOWNER    default owner username
      --not-published=NOTPUBLISHED    only for revisions not published in this environment
      --scroll-size=SCROLLSIZE        Size of the elasticsearch scroll request
      --scroll-timeout=SCROLLTIMEOUT  Time to migrate "scrollSize" items i.e. 30s or 2m
      --searchQuery[=SEARCHQUERY]    Query used to find elasticsearch records to import [default: "{}"]
```

* **environment** : name of the environment for running the es query
* **--task**: json escaped string for task definition  
* **--field-assignee**: use document value for assignee (will search username, displayName, email)
* **--field-deadline**: use document value for deadline
* **--default-owner**: if the revision has no owner, this username will be used  
* **--not-published**: only created task for revision that are not published in the given environment name.
* **--scroll-size**: Size of the elasticsearch scroll request
* **--scroll-timeout**: Time to migrate "scrollSize" items i.e. 30s or 2m
* **--search-query**: json escaped string with es query

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

## XLIFF

The core supports XLIFF exports and imports in order to have some content translated by a translation office.

### XLIFF limitation

At this point elasticms only supports XLIFF translation in separated documents. In other words a document is associated to one and only one language. Those documents needs:

 - A keyword field to identify the document's locale i.e. a `locale` field contains values like `'fr'`, `'en'`
 - A keyword field to link documents that are translation of each other. It can be a `menu_uid` referring to a JSON Menu entry or a data link

So the couple of those two fields must be unique by environment.

A support where fields such as `title_fr` and `title_nl` are in the same document is feasible but is not yet supported.

### XLIFF extraction

This command generates an XML in a [XLIFF format 1.2](http://docs.oasis-open.org/xliff/xliff-core/xliff-core.html).

If a `publish-to` is specified in the options, the command will check if something as changed in source fields between the default environment and the `publish-to` one. If nothing changed and if the target fields are defined those target's sate will be marked as `'final'`

Example:

```
emsco:xliff:extract next '{"query":{"bool":{"must":[{"term":{"_id":{"value":"db27a1da21b8d9c556abe67451007cd0ad80c54b"}}},{"terms":{"_contenttype":["instruction","additional","intermediate"]}}]}}}' nl de introduction title_short title --base-url=http://instructions-pgsql-admin-dev.localhost --target-environment=latest
```

This command will 
 - extract the fields `introduction`, `title_short` and `title`
 - for the document with the OUUID `db27a1da21b8d9c556abe67451007cd0ad80c54b` if it exists in the `next` environment
 - The expected locale of this document should be `nl`
 - It will try to identify a `de` document having the same `translation_id`  in the `latest`
   - The translatable fields of this document will be used as default target value
 - It will check if something has changed for the current revision of the document, for the translatable fields, of the revision
   - in the `latest` environment with the same OUUID
   - If nothing changed, and if a target is defined it will mark the target's state as `final`

### XLIFF update

Example:
 ```
emsco:xliff:update /tmp/ems-extract-BfHeoa.xlf --publish-to=latest --archive
```

This command will:
 - Load the XLIFF file passed as argument
 - Each source document's revisions are identified in the XLIFF file. That exact revision will be used to generate a new revision for the target locale (defined in the XLIFF file)
 - The target OUUID will be identified via an elasticsearch query looking for a single document  
   - In the `latest` environment (as it's specified in the `publish-to` option, otherwise it will look in the default environment of the revision)
   - Having the same `translation_id` field value
   - Having the locale field value set to target locale
   - If not found a new document with a brand new OUUID will be generate
 - As a `publish-to` environment is defined, translated revisions will be directly published in that environment
 - As the archive option is set the translated revisions will be unpublished from there default environment and mark as archived
   - This option is available only if a `publish-to` environment is defined