# Core Commands

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


                               
                               