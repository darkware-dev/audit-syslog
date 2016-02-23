# Audit Syslog for WordPress

Audit Syslog is a plugin designed for WordPress Multisite which interfaces with a local
syslog service to log various security-related or administrative actions on the
WordPress instance. Log messages identify the activity taken, the multisite blog involved,
the currently-authenticated username, and the remote IP of the client that made the
request.

### Logged Information

Each logging statement includes the following information:

* The multisite blog where the action took place.
* The currently-authenticated user.
* The remote IP of the client that initiated the request.
* A description of the event.

### Currently Logged Events

*(This section subject to change due to the development status of the project)*

* User login
* User logout
* Plugin activation
* Plugin deactivation
* Theme changes
* User creation
* User deletion
* Adding a user to a blog
* Removing a user from a blog
* Role assignment
* Blog creation
* Blog deletion
* Blog details changes
* Blog status changes
* File uploads

##### Coming Soon:

* Post creation
* User modification

### Extra Features 

Beyond logging the information outlined above, the functions used for
logging are available globally to other plugins or themes to use.

In determining the source IP, the plugin detects most forms of load balancing or request-
proxying (so long as they attempt to report themselves). When possible the IP remote to the
proxy is reported, rather than the IP of the proxy.

In reporting logging events to syslog, the plugin attempts to be economical about the message
length. The identifying string for the blog instance will report either the sub-directory of
the blog, or the sub-domain of the blog depending on how the multisite installation is
configured. Only the unique portion is provided. It's assumed this is sufficient for an
administrator to identify the blog in question.
  
## To Do List

* Continue to add more events for logging.
* Provide a settings page to control which events are logged.
* Allow configuration of the syslog facility that is used.
* Add optimization to cache some values within a request.

Longer term wishes:

* Support standalone WordPress installs
* Support a local view of recent logging statements on the blog Dashboard.
