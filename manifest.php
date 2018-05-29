<?php ?>

acceptable_sugar_versions
	regex_matches
		- 7\.[0-9]\.[0-9]+[a-z]?
		- 8\.[0-9]\.[0-9]+[a-z]?
		
name : IAH Finance (QB)
description: Synchronization with QuickBooks
author: 1 CRM Corp.
published_date: 2014-08-30
version: 3.2.13
type: module
is_uninstallable: true
id: QBLink

copy

	--
		from: modules/QBLink
		to: modules/QBLink
	--
		from: root/
		to: ""
	--
		from: images/QBLink.gif
		to: themes/Default/images/QBLink.gif

