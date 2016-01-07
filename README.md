# Anti Brute Force #

Version: 2.0.x

> Secure your Symphony backend against brute force and dictionary attacks

Prevents ***people and softwares*** to brute force your authors/developers accounts.

### SPECS ###

- After **x** failed attempt, the IP address will be banned for **y** min;  
  **x** and **y** are settings in the preferences page 
- Features colored list: ***Black list***, **Gray list**, *White list*.
- Features a **unban via email** capabilities; Must be enabled in the preferences page
- Backend content page for managing blocked IPs and colored lists
- A Facade/Singleton class -ABF- for developers to leverage anti_brute_force capabilities
  (ex.: email reports or use with the member extension)

### NOTES ABOUT PROXIES

If you are using Symphony on a server that sits behind a proxy, it will always
track 127.0.0.1 (or your proxy's IP) as remote address, simply because PHP doesn't see anything else
in `$_SERVER['REMOTE_ADDR']`. In order to fix this, please set the 'remote-addr-key'
setting to the field set by your proxy in order to let ABF access the real user IP.
You can also set this value in Symphony's settings backend page.

Most proxies will set the 'HTTP_X_FORWARDED_FOR' field with the respective user's IP
but some other provider (such as CloudFlare) will create a custom field. Your best bet
would be to do some actual penetration testing to be sure ABF works properly.

### REQUIREMENTS ###

- Symphony CMS version 2.4 and up (as of the day of the last release of this extension)

### INSTALLATION ###

- `git clone` / download and unpack the tarball file
- (re)Name the folder ***anti_brute_force***
- Put into the extension directory
- Enable/install just like any other extension (@see <http://getsymphony.com/learn/tasks/view/install-an-extension/>)
- (optional) Go to the *Preferences* page to customize settings
	- Maximum failed count before user gets banned
	- Banned duration - number of minutes IP is banned
	- Gray list threshold - maximum number of gray list entries before black list
	- Gray list duration - in days - before expire
	- Unban via email - Enables/disable this feature
	- Restrict access from authors - Hide/Show ABF content page to Authors
	- Remote IP address field name - The `getenv()` field to look for the client's IP.
- (optional) See all the banned IPs via Anti Brute Force -> Banned IPs
- (optional) Manage colored lists entries via Anti Brute Force -> Black/Gray/White list

### UPDATING ###

Updating from >= 1.3 is safe.
[Click here for older releases](https://github.com/Solutions-Nitriques/anti_brute_force/releases).

### LICENSE

[MIT](http://deuxhuithuit.mit-license.org)

Made in Montréal with love by [Deux Huit Huit](http://deuxhuithuit.com/)
