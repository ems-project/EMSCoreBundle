# Elasticms documentation

## Content Management

### Versioning

Documents share a version **uuid** and have a **from** and **to** date.
The document without to date is the current version. All revisions have a **version_tag** defined.

If a content type has at least one version tag, revisions versioning will be enabled.
You also can defined the date from and to fields.

Finalization is now called "publish in preview" (preview = contentType default environment).
A modal will popup on publication of a revision. The author needs to select a version_tag or silent.

If the author selects a version_tag:
- a new document is created with the same version uuid and changes
- the from date field is set to now()
- the current draft is discard
- new draft is initialized and finalized with to date now()

When the user selects 'silent', the revision will just be finalized. 
Silent publication is not available for new document, because each revision needs a version_tag.

The from and to dateField are managed by ems and can not be updated by the user.

#### Migration

If you want to enable versioning for an existing content type with documents.
The from date will be the creation date and the first version tag will be used as version tag. 

```bash
php bin/console ems:environment:updatemetafield preview
php bin/console ems:env:rebuild preview
```

### Document Tasks
For enabling the task feature you have to specify the **owner role** on a content type.

#### Owners
Users that have the owner role for a content type can become owner for documents of this content type.
Important you can only become the owner if there are no tasks defined for the document and you create the first one.
Document owners will see the tasks tab and can create, update, delete, reorder, approve or reject tasks.
If the tasks is assigned to the owner he can only finish the task.

Document owners have a dashboard of 2 tabs:
- Assigned to me : document owners can also be assigned users
- Created tasks: all current tasks for documents they own.

#### Assigned Users
If a document owner assigns you a task you are a assigned user. You will see your assigned task on your dashboard.
Assigned users can see they history of the current task and after completing the task request a validation from the owner.
If the document owner rejects the task, the assigned user will be notified by email.

#### Task Managers
Elasticms users who have the role **'TASK_MANAGER'** can see all current tasks in their dashboard overview.
They have the possiblity to change the ownership of the documents.





 