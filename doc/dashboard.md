# Dashboard

Dashboards are customizable views that can apply to many content types at the same times:

A dashboard id defined by
 - A name (webalized for generating the url)
 - A role to allow access
 - An icon
 - A label for displaying
 - A color (for the link in the sidebar menu nor notification menu)
 - A checkbox to display a dashboard link in the sidebar menu
 - A checkbox to display a dashboard link in the notification menu
 - A type of dashboard
 - Type specific options (see bellow)

A dashboard can be used 
 - as quick search
 - as landing page


## Dashboard types

### Template

Generate a DOM inside the elasticms interface. You can use all twig filters and functions.

Options:
- Body: DOM (Twig format) that will be injected in the content block of the elasticms interface
- Header: DOM (Twig format) that will be injected at the end of the header tag. Useful to define or override some CSS.
- Footer: DOM (Twig format) that will be injected at the end of the body tag. Useful to inject javascript codes.


### Export
Generate a DOM outside the elasticms interface. You can use all twig filters and functions.

Options:
- Body: DOM (Twig format) that will be injected in the content block of the elasticms interface
- Filename: Text (Twig format) that will use to generate the file name.
- An optional mimetype
- Disposition: File disposition: Attachment or Inline.


### Upcoming dashboards

Here a list of dashboard types that we may  develop in the future:

- logs: a tools to have access to logs (filtered fir the current user or severity or not)
- analytics: integration with analytics tools such Google Analytics, Matomo, ...
- structure: A structure tools that organise documents in a structure (i.e. linked to a path_en field)
- calendar
- maps
- gantt
- advance search
- notification
- tasks
- jobs
- link/shortcut
- shopping basket
- redirect
- ...
