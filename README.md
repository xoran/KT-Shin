![Shinterest logo](http://www.warriordudimanche.net/data/images/shinterest.png)


Shaarli + pinterest aspect (and more)

New in this version:

- Added multi language support 
  - add your own in inc/languages.php. 
  - change language in the inc/configuration.php (no time to add it to config page)
  - to add messages to a template use {"message in english"|e} and add a line to languages.php in the $massages array: 'message in english'=>'message en français', (caution: the array's key is always lowercase event if message in template is not !)

- responsive pinterest aspect (widgets columns)
	- uses css3 column property & media queries
	- added possibility to specify an img url for the links (as an illustration)

- added a "notebook" mode to write or edit a note (easy and fast) (see tools)
	- new bookmarklet 
	- new favlink to go directly into the notebook part
	- markdown (not only for notebook part). Uses https://github.com/broncowdd/parsedown (see in inc/functions.php)

- Some changes in the tag cloud page
  - the tag's size is changed with a class and no longer with an inline style
  - you can target the sizes like you want; if a tag is used only one time, its class is size0, so you can hide it from shaarli.css
  - the admin can now delete a tag directly from the tag cloud. This feature uses the same security system used by SebSauvage (token)
    (if not logged, the delete button doesn't appears)

- Added the higlighting of search terms

- Ddded body css clases to easily target every page and every tag result page:
    - .addlink
    - .addnote
    - .changepassword
    - .changetag
    - .configure
    - .daily
    - .editlink
    - .login
    - .picwall
- Cleaned some inline css rules and replaced them into shaarli.css

- Structural changes:
  I cuted the index file into several parts to make easier the futures changes and the futures updates 
	Now, there is 
	- inc/configuration.php
	- inc/functions.php
	- inc/initialisation.php

	those files are here to add some features to shaarli without important changes in the index.php file
	- inc/private_get_commands.php
	- inc/public_get_commands.php
	- inc/personal_post_actions.php
	- inc/render_basic_page.php


Caution ! I clearly don't give a shit of IE... this version is NOT specificly designed for this "browser"
I've tested it on Firefox, Chromium  & opera...
Tell me if you see something wrong ;-)



