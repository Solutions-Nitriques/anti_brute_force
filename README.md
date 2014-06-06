# Anti Brute Force #

Version: 1.4

## Secure your Symphony backend against brute force and dictionary attacks ##

Prevents ***people and softwares*** to brute force your authors/developers accounts.  

### SPECS ###

- After **x** failed attempt, the IP address will be banned for **y** min;  
  **x** and **y** are settings in the preferences page 
- Features colored list: ***Black list***, **Grey list**, *White list*.
- Features a **unban via email** capabilities; Must be enabled in the preferences page
- Backend content page for managing blocked IPs and colored lists
- A Facade/Singleton class -ABF- for developers to leverage anti_brute_force capabilities
  (ex.: email reports or use with the member extension)

### REQUIREMENTS ###

- Symphony CMS version 2.4 and up (as of the day of the last release of this extension)

### INSTALLATION ###

#### Beware, you will loose settings after upgrading from < 1.0.2 ####
***You must uninstall all previous version and install the new one***

- `git clone` / download and unpack the tarball file
- (re)Name the folder ***anti_brute_force***
- Put into the extension directory
- Enable/install just like any other extension (@see <http://getsymphony.com/learn/tasks/view/install-an-extension/>)
- (optional) Go to the *Preferences* page to customize settings
	- Maximum failed count before user gets banned
	- Banned duration - number of minutes IP is banned
	- Grey list threshold - maximum number of gray list entries before black list
	- Grey list duration - in days - before expire
	- Unban via email - Enables/disable this feature
	- Restrict access from authors - Hide/Show ABF content page to Authors
- (optional) See all the banned IPs via Anti Brute Force -> Banned IPs
- (optional) Manage colored lists entries via Anti Brute Force -> Black/Grey/White list

*Voila !*

<http://www.deuxhuithuit.com/>
