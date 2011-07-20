# Anti Brute Force #

Version: 1.1

## Secure your Symphony backend login page against brute force attacks ##

Prevents ***people and softwares*** to brute force your authors accounts.  

### SPECS ###

- After **x** failed attempt, the IP address will be banned for **y** min;  
  **x** and **y** are settings in the preferences page 
- Features colored list: ***Black list***, **Grey list**, *White list*.
- Features a **unban via email** capabilities; Must be enabled in the preferences page
- Backend content page for managing blocked IPs and colored lists
- A Facade/Singleton class -ABF- for developers to leverage anti_brute_force capabilities
  (ex.: email reports or use with the member extension)

### REQUIREMENTS ###

- Symphony CMS version 2.2 and up (as of the day of the last release of this extension)

### INSTALLATION ###

#### Beware, you will loose settings after upgrading from < 1.0.2 ####
***You must uninstall all previous version and install the new one***

- Unzip the anti_brute_force.zip file
- (re)Name the folder ***anti_brute_force***
- Put into the extension directory
- Enable/install just like any other extension
- (optional) Go to the *Preferences* page to customize settings
	- Maximum failed count before user gets banned
	- Banned duration - number of minutes IP is banned
	- Grey list threshold - maximum number of grey list entries before black list
	- Grey list duration - in days - before expire
	- Unban via email - Enables/disable this feature
- (optional) See all the banned IPs via System menu -> Banned IPs
- (optional) Manage colored lists entries via System menu -> Colored lists

*Voila !*

http://www.nitriques.com/open-source/

### History ###

- 1.1 - 2011-07-17  
  Colored list feature added  
  Fix issues #5, and #7  

- 1.0.2 - 2011-07-02  
  New data base scheme  
  New setting group, which was a copy/paste error -- breaks downward compatibility --  
  Fix others errors (not bugs, errors): issue  #3, #4, #6

- 1.0.1 - 2011-07-02    
  Fix Issues #1 (typo) and #2 (no more ASDC)  

- 1.0 - 2011-07-01    
  First release: Block login, Admin content page, ABF Facade/Singleton  