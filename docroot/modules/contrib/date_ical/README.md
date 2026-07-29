Date iCal

This module allows users to export iCal feeds using Views, and import iCal feeds
from other sites using Feeds. Any entity that contains a Date field can act as
the source/target to export/import an iCal feed.

## INSTALLATION

Date iCal has several required dependencies, and an optional one:

- The Views (version 3.5+), Entity API, Libraries API (version 2.0+), and Date
  modules are required.
- The iCalcreator library v2.20.2 is required; for PHP 7.4 compatibility, use
  v2.20.4.
- PHP 5.3 is required for the iCalcreator library to properly handle timezones.
- The Feeds module is optional. It's needed only if you wish to import iCal
  feeds from other sites.

## EXPORTING AN ICAL FEED USING Views

There are two plugins that export iCal feeds. You can use either one, though
the iCal Fields plugin (described later) is a bit more versatile.

HOW TO EXPORT AN ICAL FEED USING THE iCal Entities PLUGIN

1. Go to the Manage Display page for the content type you want to export in an
   iCal feed. On the "Default" tab, check the box for "iCal" in the section
   titled "Use custom display settings for the following view modes", then
   click Save.
2. Click the new "iCal" tab that now appears in the upper-right corner of the
   Manage Display page for this content type.
3. Set up the iCal view mode to contain whatever should be exported as the
   'Description' field for the iCal feed. You can trim the text to the desired
   size, include additional information from other fields, etc.
4. Do this for each of the content types that you wish to include in your
   site's iCal feeds.
5. Create a new View that displays the entities that you want to include in
   the iCal feed.
6. Add a "Feed" display to the same View. Change the Format to "iCal Feed".
   When you click "Apply" from that dialog, you'll be given the option to name
   the calendar, which will appear in your users' calendar clients as the
   calendar's title.
7. Change the Show setting to "iCal Entity".
8. In the settings for iCal Entity, select the date field that should be used
   as the event date for the iCal feed. Make sure that you choose a field that
   is a part of every entity that your View displays. Otherwise, the entities
   which don't have that field will be left out of the iCal feed.
9. You may optionally choose a field that will be used to populate the
   Location property of events in your iCal feed. This field can be a text
   field, a Node Reference field, an Addressfield, or a Location field.
10. Give the Feed a path like 'calendar/%/export.ics', including a '/%/' for
    every contextual filter in the view.
11. Make sure the Pager options are set to "Display all items".
12. Add date filters or arguments that will constrain the view to the items you
    want to be included in the iCal feed.
13. Using the "Attach to:" setting in the Feed Settings panel, attach the feed
    to a another display in the same view (usually a Page display). Be aware,
    though, that the Feed will display exactly what its settings tell it to,
    regardless of how the Page display is set up. Thus, it's best to ensure
    that both displays are configured to include the same content.
14. Save the View.
15. Navigate to a page which displays the view (usually the Page display's
    "path" setting). You should see the iCal icon at the bottom of the view's
    output. Clicking on the icon will subscribe your calendar app to the iCal
    feed.
16. If you don't have a calendar app set up on your computer, or you want your
    users to download the ical feed rather than subscribe to it, you'll want to
    go back to the View settings page, click the Settings link next to
    "Format: iCal Feed", and check "Disable webcal://". Then save your View.
    This will make the iCal icon download a .ics file with the events, instead
    of loading the events directly into the user's calendar app.
17. If events that you expect your feed to include are not appearing when it
    gets consumed by a calendar app, check the Drupal permissions for your
    event content type. If anonymous users can't view the event nodes, they
    won't appear in your feed when it gets loaded by a calendar app.

## HOW TO EXPORT AN ICAL FEED USING THE iCal Fields PLUGIN

1-6.These steps are the same as above.

7. Add views fields for each piece of information that you want to populate
   your iCal feed with. A Date field is required, and fields that will act as
   the Title and Description of the events are recommended. You can also
   include a Location field.
8. Back in the FORMAT section, change the "Show" setting to 'iCal Fields'.
9. In the settings for iCal Fields, choose which views fields you want to use
   for the Date, Title, Description, and Location.
10. These steps are the same as above.

## Use Service

$vcalendar = \Drupal::service('date_ical.feed')->feed($events, $header);

$header = [
'RELCALID' => 'ID-Unique'
'TIMEZONE' => 'Time Zone'
'CALNAME' => 'Calendar name'
'CALDESC' => 'Calendar description'
]

$events = [
  [
    "date_field" => [
      "value" => "2023-11-16T00:45:14",
      "end_value" => "Optional"
    ],
    "created" => "2023-11-22T00:43:55",
    "last-modified" => "2023-11-24T01:30:30",
    "summary_field" => "Summary event",
    "description_field" => "Description event",
    "location_field" => "Location of event",
    "categories_field" => "task",
    "organizer_field" => [
      [
        "mail" => "one@person.email",
        "name" => "Array but Only one person"
      ]
    ],
    "status_field" => "CONFIRMED",
    "url_field" => "http://example.com",
    "attendee_field" => [
      [
        "mail" => "1st@person.email",
        "name" => "First person"
      ],
      [
        "mail" => "2nd@person.email,
        "name" => "Second person"
      ]
    ],
    "alarm_field" => "P1M1D",
    "attach_field" => [
      "url to file",
    ],
    "geo_field" => [
      "lat" => 16.25259,
      "lon" => -61.55473,
    ]
  ],
]
