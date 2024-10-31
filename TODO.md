# TODO

The following list comprises ideas, suggestions, and known issues, all of which are in consideration for possible implementation in future releases.

***This is not a roadmap or a task list.*** Just because something is listed does not necessarily mean it will ever actually get implemented. Some might be bad ideas. Some might be impractical. Some might either not benefit enough users to justify the effort or might negatively impact too many existing users. Or I may not have the time to devote to the task.

* Add filter for IP address prior to display
* Make IP in admin table a link that allows for linking to a listing filtered by posts with that IP
* Show a count of posts matching that IP address. (Make optional somehow, and likely disabled by default, e.g. filter or screen option.) In "All" view, maybe add a dropdown or hover text that breaks the count down by post status.
* Allow filtering table by IP address to support starts-with partial IP address, e.g. ?c2c-post-author-ip=192.168.1 (facilitated by a search field somewhere)
  See: https://wordpress.org/support/topic/search-posts-by-author-ip/
* Who can see IP? If any author, maybe restrict to admins by default and/or special caps and/or filter
* Delete associated post author IP addresses when deleting a user?
  * If so, consider adding a hook to allow overriding this behavior
    * Maybe hook the text to replace the IP address that gets "deleted", default to "(user deleted)". If a label is provided, then replace the IP address with the label. If true is provided, delete with no label. If false, then don't delete, retaining IP address.
  * Can be a plugin setting
* Add button for admins to use to "Delete associated post author IP addresses?" on profiles for post authors?
* Allow IP addresses to get linked to some IP address lookup service whereby the IP address gets passed. A setting would be necessary to define the link format (e.g. "https://ip-address-lookup.example.com/?ip=%IP%") where "%IP%" represents the IP address and must be provided for the link to be considered valid. Maybe suggest one or two, but don't default to any.
* Consider opt-in into an IP address info service that can be used to provide info about IP address in a popup.

Feel free to make your own suggestions or champion for something already on the list (via the [plugin's support forum on WordPress.org](https://wordpress.org/support/plugin/post-author-ip/) or on [GitHub](https://github.com/coffee2code/post-author-ip/) as an issue or PR).