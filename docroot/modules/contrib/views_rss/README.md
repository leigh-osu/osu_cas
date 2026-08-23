# Advanced Views RSS Feed

Provides a replacement RSS display system for Views for building customized RSS
feeds.


## Features


## Setup

* Enable the "Advanced Views RSS Feed" and "Advanced Views RSS Feed: Core
  Elements" modules.
* Create a new View, or customize an existing one.
* Add a Feed display to the view.
* Change the display "Format" option to "Advanced RSS feed".
* Change the display "Show" option to "Advanced RSS feed".
* Assign an appropriate path for the feed via the "Feed settings" -> "Path"
  option.
* Add fields to the display. Each field will be available for use on elements
  on the RSS feed.
* Open the "Settings" dialog for the the display "Format" option.
* Expand the "Channel elements : core" fieldset and fill in the values as
  appropriate.
* Expand the "Other feed settings" fieldset and adjust the settings as needed.
* Click "Apply" to apply the changes.
* Open the "Settings" dialog for the display "Show" option.
* Expand the "Item elements: core" fieldset and for each element that is needed
  select the appropriate field.
* Click "Apply" to apply the changes.
* Add more fields and assign them in the "Show" settings as necessary.
* Save the changes.
* Review the output and continue making changes as necessary.


## Troubleshooting

### The channel image link tag is incorrect

If the channel->image->link tag outputs as `imagelink` instead of just `link`,
the problem is that the module's custom `views-view-rss.html.twig` file is not
being used. To fix this, either copy that file to the theme's "templates"
directory, or update the theme's twig file to match the following:

    {{ channel_elements|render|replace({"imagelink": "link"})|raw }}

This is safe to do as the content passes through Drupal's render system first,
which will filter out any security concerns.


### RSS validators complain of an invalid "imagelink" tag

See "The channel image link tag is incorrect" above.


## Support

Please post questions, bug reports and feature reports to the [project's issue
queue](https://www.drupal.org/project/issues/views_rss).
