# Google Calendar Events
This Drupal 9 module syncs locally created `Event` nodes with the Google Calendar of a configured calendar.

## Features
- Retrieves events from the Google Calendar API for a configured calendar, and creates an `Event` node for each one.
- When the time is updated on the configured calendar, that change is eventually detected and updated in the corresponding Drupal `Event` node.
- An out of the box view is included to display `Event` nodes in a calendar view.

## Installation
There are a few dependencies you'll need to get first before installing the module. You'll need the following Drupal modules:

- [Full Calendar View](https://www.drupal.org/project/fullcalendar_view)
- [Key](https://www.drupal.org/project/key)
- [Smart Date](https://www.drupal.org/project/smart_date)
- Smart Date Recur (included with Smart Date)

Once those are installed, go ahead and install the module itself:

`composer require tributemedia/google_calendar_events`

After the install, make sure one final composer dependency is installed called `google/apiclient`. If you don't see that it was installed during the install of the module itself, you'll want to install it.

`composer require google/apiclient`

Finally, you're ready to install the module in the Drupal interface! Go ahead and do so, there should be no issues at this point.

## Configuration

### Service Account & Key

Now that the module is installed, it must be configured before it's ready to use. If you haven't already, go to the Google console and create a project, and within that project create a service account and then give it a service key. Here are some instructions if you don't know how to do that:

- [Creating a service account](https://cloud.google.com/iam/docs/creating-managing-service-accounts).
- [Creating a service key](https://cloud.google.com/iam/docs/creating-managing-service-account-keys).

Once you have those, in the admin menu, navigate to Configuration -> System -> Keys. Select 'Add Key'. Fill out as such:

- Key Name: Google Calendar Service Key (**IMPORTANT:** The key name must match this exactly, and have the machine name google_calendar_service_key. If a key with this exact name is not detected, the module won't work!)
- Key type: Authentication.
- Key provider: Configuration.
- Key value: Copy the service key you obtained and paste it here.

If a field wasn't included in the list, you can leave it blank. You can now save and the key will be available to the module.

Just one last thing with the service account! You'll need to share the calendar you want to have synced with the service account. Each service account in Google has an associated, spammy looking email address. You'll need to find that and share the calendar in question with it.

### Module Config

Now we're ready to config the module itself! In the admin menu, navigate to Configuration -> Web services -> Google Calendar Events Settings. If you configured the service key correctly, you'll see a message prompting you for an API Subject. This is simply the email address of a person who manages the service account you created. Go ahead and supply that in this field, save, and if everything is configured correctly, you'll have additional self-explanatory options available! You can pick from multiple calendars, if your service key and API subject have access to them.

## Usage

Using the module after configuration is simple! Events will be synced automatically every 12 hours, but if you want a run sooner than that (especially for your first batch of events!), then you can navigate to Configuration -> Web services -> Google Calendar Events Status. Once here, click the 'Check Events' button, and you'll have queued up a cron job for the module to check for events. You can then run cron to get the events.

You also have access to a built-in view to display events, once you have them. The view has a page display configured at the path `/events`.

### Cron Workflow

In case you're curious about the cron workflow, there are two workers. The first checks for new events, or updated ones, when a job is queued to the `google_calendar_events_ccq`. CCQ stands for Calendar Check Queue.

If the CCQ worker detects a new events, or the need to update an existing one, a job is queued on the `google_calendar_events_ceq`. CEQ is short for Calendar Event Queue. The CEQ's job is to then create a new event, or update the existing one with the new start and/or end times.
