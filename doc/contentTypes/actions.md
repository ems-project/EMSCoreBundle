# Content Type: Actions

Render option | description
--- | ---
[Embed](#embed) | Create a custom action page
[Export](#export) | Create an export file (csv, xml, ..)
[External link](#external-link) | Create an external link
[RawHTML](#raw-html) | Custom raw html action
[Notification](#notification) | Create a new notification
[Job](#job) | Start a new job
[PDF](#pdf) | Generate a pdf 

## Embed
The body is used for creating a new page. 
Good for generating overviews or custom reports.

## Export
Export a generated file.

## External link
The body is the href attribute for the external link.
You can also use the raw render option for more flexibility.

## Raw HTML
Only if the body returns html the output will be visible. 
With the HTML render option you can even overwrite the icon.

## Notification
Creates a new ems notification

## Job
Start a new job, the body should be the command with arguments and options.

## Pdf
Similar to the export render option, but will always generate a pdf.


