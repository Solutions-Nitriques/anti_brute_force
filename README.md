# Anti Brute Force #

Version: 1.1

## Secure your Symphony backend login page against brute force attacks ##

Prevents ***people and softwares*** to brute force your authors accounts.  

### SPECS ###

- After **x** failed attempt, the IP address will be banned for **y** min;  
  **x** and **y** are settings in the preferences page 
- Features colored list: ***Black list***, **Grey list**, *White list*.
- Features a "unban via email" capabilities; Must be enabled in the preferences page
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
- (optional) See all the banned IPs via System menu -> Banned IPs

*Voila !*

http://www.nitriques.com/open-source/

### History ###

- 1.1 - 2011-07-xx
  Colored list feature

- 1.0.2 - 2011-07-02
  New data base scheme
  New setting group, which was a copy/paste error -- breaks downward compatibility --
  Fix others errors (not bugs, errors)  

- 1.0.1 - 2011-07-02  
  Fix Issues #1 (typo) and #2 (no more ASDC)

- 1.0 - 2011-07-01  
  First release: Block login, Admin content page, ABF Facade/Singleton