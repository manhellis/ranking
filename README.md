EGroupware ranking or result-service
====================================

**ranking** is an [EGroupware](https://www.egroupware.org/) application allowing sport federations to manage sub-federations, athletes, cups, competitions, results and rankings.
result-service allows to interactiv enter results / judge competitions on desktop or mobile devices.

Following documents content of public JSON and/or XML feeds.

Result data via XML or JSON
===========================
So here's a small documentation. I will use XML as example because it's
easier to view (eg. in Firefox or Chrome) compared with the JSON, thought content
is identical and you only have to replace xml.php with json.php in the
URL's to get JSON. There's no difference in content or update frequency.

You can use a few GET parameters to influence what information you get:

a) comp: This is the Id of the competition (1287 in case of Arco
Rockmaster 2010). Other competitions id's can be found the the url's of
eg. [DAV](https://www.digitalrock.de/dav_calendar.php?no_dav=1) or [SAC](https://www.digitalrock.de/sac_calendar.php)
competition calendar or [DAV](https://www.digitalrock.de/egw/ranking/sitemgr/digitalrock/results.html?nation=GER) or [SAC](https://www.digitalrock.de/egw/ranking/sitemgr/digitalrock/results.html?nation=SUI) competition calendar

b) cat: This is id of the category and discipline:
```
1 = MEN lead
2 = WOMEN lead
5 = WOMEN bouldering
6 = MEN bouldering
23 = MEN speed
24 = WOMEN speed
70 = MIXED team relay speed
71 = WOMEN team relay speed
72 = MEN team relay speed
```
(You can lookup the used categories of a competition in the calendar or
latest results page).

c) route: The heat of a category result, or if ommited general result:
```
-1 = general result
0  = qualification
1  = 2. qualification (if used, probably not in Arco)
2  = further heats 'til the final depending on discipline
N  = final (N-1 in speed is eg. small final)
```

d) type=result instead of route, to fetch result from ranking, not result-service
This is done automatic if there is no result in result-service.
If result has been filtered on import (eg. EYC result or international or national open competitions),
this contains the filtered result, with result-service contains the full result.

Examples:
--------
1) lead competition general result:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1204&cat=1),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1204&cat=1),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&debug=1)

2) lead competition 1. qualification:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1204&cat=1&route=0),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1204&cat=1&route=0),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=0),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=0&debug=1)

3) lead competition 2. qualification:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1204&cat=1&route=1),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1204&cat=1&route=1),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=1),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=1&debug=1)

4) lead competition 1/2-final:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1204&cat=1&route=2),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1204&cat=1&route=2),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=2),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=2&debug=1)

5) lead competition final:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1204&cat=1&route=3),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1204&cat=1&route=3),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=3),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1204&cat=1&route=3&debug=1)

6) boulder competition general result (1 qualification):
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1247&cat=5),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1247&cat=5),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1247&cat=5),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1247&cat=5&debug=1)

7) boulder competition general result (2 qualification):
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1258&cat=5),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1258&cat=5),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1258&cat=5),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1258&cat=5&debug=1)

8) boulder competition 1. qualification
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1258&cat=5&route=0),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1258&cat=5&route=0),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1258&cat=5&route=0),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1258&cat=5&route=0&debug=1),
further heats replace route=0 in URL with 1, 2, ...

9) speed competition general result:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1254&cat=23),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1254&cat=23),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1254&cat=23),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1254&cat=23&debug=1)

10) speed qualification:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1254&cat=23&route=0),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1254&cat=23&route=0),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1254&cat=23&route=0),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=1254&cat=23&route=0&debug=1)
(In case of a 2. qualification route, xml contains additional result_l
and result_r attributes, with the sum in result)

11) speed final heats: replace route=0 in above URL with route=2, 3, 4, ...

12) speedrelay general result:
[HTML](https://www.digitalrock.de/egroupware/ranking/result.php?comp=991&cat=70)
Please note the changed HTML url, as eliste.html does not yet support
it. There can by either 2 (mixed) or 3 team members!
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?comp=991&cat=70)
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?comp=991&cat=70)
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?comp=991&cat=70&debug=1)
Participants array/hierarchy contains now teams, with in turn contain an
athletes hierarchy with the team-members and their single results.

13) team relay heats: append &route=2, 3, 4, ...

Explanation of xml/json attributes:
----------------------------------
- WetId: equivalent of comp GET parameter, id of competition
- GrpId:  equivalent of cat GET parameter, id of category and discipline
- route_order: identical to route GET parameter
- route_name: name of heat including category
- route_names: name and route id of heats (general result / route=-1 only)
- route_result: time the result was made official by jury, if not given
- results should be displayed as "temporary"
- discipline: lead, speed, boulder or speedrelay
- participant(s): hierarchy of participants sorted by rank, for start-lists
	you need to sort them by start_order
- start_order: order athletes are starting (1, 2, ...)
- start_number: number displayed by athlete (if used)
- result_rank: rank of athlete, if already ranked
- PerId: id of athlete
- url: url to profile page of athlete
- lastname, firstname, nation, federation: pretty much self explanatory
- result: result of competitor for given heat (not set for general result)
- rank_prev_heat: result&rank of previous heat if counting in countback
- resultN: N=0, 1, 2, ... result in heat N (general result only)
- result_rankN: N=0, 1, 2, ... rank in heat N (general result only)
- team_id, team_name, team_nation: id, name and nation for speedrelay
- athletes: hierarchy of athletes of the team including their single
	results (sum is in result attribute of team)
- current(s): hierarchy with current participant(s) referencing participants via PerId
```xml
<participants>
	<participant>
		<PerId>123</PerId>
		<!-- further data -->
	</participant>
	<!-- further paticipants -->
</participants>
<currents>
	<current>123</current><!-- always present for all disciplines -->
	<current>234</current><!-- right/2. climber speed or 2. boulder -->
	<current>345</current><!-- 3. boulder -->
	<!-- further boulders -->
</current>
```
Please note that Arco Rockmaster might not always use the regular
worldcup format, so there can be differences to the examples I gave!

The HTML examples (eliste.html) I gave use a javascript API (using the
described webservice via JSON). The API can be used to embed results
into other webpages eg. the webcast page. They have an update method
which can be called on non offical results to self-update on a certain
frequency (NOT less then 10s!).

Calendar data via XML or JSON
=============================
calendar data feed is triggered by setting "nation" GET parameter, or NOT setting "comp" parameter.
Optional parameters:
- year: to switch to a calendar of a different year then the current one
- filter: allows to filter competitions by a given column and value(s),
	eg. "filter[cat_id]=!71,72" returns only competitions not having cat_id=71 or 72
	you can use most fields below to filter by

Meaning of competition values:
- WetId: numerical id, used eg. as "comp" parameter for result calls
- rkey: unique short name of competition
- name: long competition name
- short: short competition name
- nation: calendar nation, empty for international competition
- homepage: url of competition homepage
- host_nation: 3-letter code of host-nation, eg. "GER"
- deadline: registration deadline in YYYY-MM-DD format
- discipline: discipline (if specified on competition, usually not for international competition) "lead", "speed" or "boulder"
- cat_id: numerical competition (not athlete!) category
- fed_id: only return competitions from given federation (eg. state within Germany)
- display_athlete: how athlet is to be displayed: "nation" (default), "federation", "nation_pc_city", "pc_city"
- selfregister: 0 = no selfregistration, 1 = selfregistration, 2 = selfregistration with confirmation by federation
- open_comp: comp-id of competition this one is an open for
- duration: of competition in days
- date: start-date of competition in YYYY-MM-DD format
- date_end: end-date of competition
- date_span: eg. 13. - 14. April 2012
- cats: categories of competition with values:
  * GrpId: numerical id, used eg. as "cat" parameter
  * name: name of category eg. "W O M E N  boulder"
  * status: data available
    - 0: result imported into ranking
    - 1: result-service result available
    - 2: result-service startlist available
    - 3: old non result-server startlist (no longer used)
    - 4: starters / registration data
  * url: to display data
- info: url to info pdf
- info2: url to 2. info pdf
- result: url to result as pdf, if one got uploaded
- startlist: url to startlist as pdf, if one got uploaded
- years: array of year's for which a calendar is available

Ranking data via XML or JSON
============================
ranking data is triggered by setting "cat" GET parameter and NOT setting "comp" parameter.
Optional parameters:
- date: YYYY-MM-DD value or WetId of a competition, to get eg. ranking used for distribution of participants on 2 routes
- cup: numerical id of cup or string rkey, for a cup ranking

Ranking data is (on purpose) very similar to competition results:
- cat: GrpId & name: numerical id and name of category
- cup: SerId & name: numerical id and name of cup
- comp: WetId, name & date: of last competition in ranking
- max_comp: max. number of competitions counting
- start: start of ranking window (not for cup ranking!)
- end: date of ranking (might be different from passed in date, as ranking are only published for competition dates!)
- participants: with following values:
  * PerId: numerical id of athlete
  * firstname, lastname, birthyear, nation, city of athlete
  * federation: name of federation
  * fed_url: url of federation
  * url: profile url
  * result_rank: rank in ranking
  * points: sum of counting competitions
  * result{WetId}: "{rank}.\n{points}" (eg. "1.\n100.00") rank and points (in brackets if not counting) for competition {WetId}
- route_name: name of competition
- route_names: multiple numerical id {WetId}: "{short name}\n{date}" of competition in ranking, can be used as column-headers
- route_result: date of ranking, same as end
- comp_name: cup name or "Ranking": category name
- route_order: always -1
- discipline: always "ranking"

You can use similar code / widgets to display rankings as you use for results.

Examples:
--------
1. Current worldranking MEN lead:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html?cat=1),
[HTML with results](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html?cat=1&detail=1),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?cat=1),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?cat=1),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?cat=1&debug=1)
2. 2015 WorldCup WOMEN lead:
[HTML](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html?cat=2&cup=15_wc),
[HTML with results](https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html?cat=2&cup=15_wc&detail=1),
[XML](https://www.digitalrock.de/egroupware/ranking/xml.php?cat=2&cup=15_wc),
[JSON](https://www.digitalrock.de/egroupware/ranking/json.php?cat=2&cup=15_wc),
[JSON pretty-print](https://www.digitalrock.de/egroupware/ranking/json.php?cat=1&debug=1)

(Latest) competition results from all categories via XML or JSON
================================================================
This data is NOT from result-service, but from data (eg. from result-service) imported into the ranking.
It contains first N climbers (plus additionally all from a given nation) of ALL categories of a competition.

Examples:
--------
- HTML: https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/eliste.html#comp=1316
- XML:  https://www.digitalrock.de/egroupware/ranking/xml.php?comp=1316
- JSON: https://www.digitalrock.de/egroupware/ranking/json.php?comp=1316

Feed supports the following parameters:
- comp: numeric competition id, or "." for latest international comp. or 3-char calendar nation eg. "GER" for latest German comp.
- num: number of results to return, default 8 for 2 or less categories, 3 for more then 2 categories
- all: 3-char nation code, to include all athletes from given nation
- filter[...] as for calendar, eg. filter[cat_id]=69 to include only latest WorldCup and Championshipts (but no youth or int. events)

Data include usual competition data as eg. in calendar, plus categorys array containing following data for each category
of the competition with values for:
- GrpId numeric category id, eg. 5
- name of category, eg. "M E N  boulder"
- rkey unique string key of category, eg. ICC_FB
- url to full result page
- results array with following values of first N athletes:
  * result_rank rank
  * PerId: numerical id of athlete
  * firstname, lastname, birthyear, nation, city of athlete
  * federation: name of federation
  * fed_url: url of federation
  * url: profile url
Plus competitions array with following values for last 20 competitions (eg. to implement a competition chooser):
- WetId numeric competition id
- name of competition
- nation calendar nation (empty for internation competitions)
- date and date_span, eg. "2012-11-02" and "2. - 4. November 2012"

Registration aka Starters from a competiton via XML or JSON
===========================================================

Examples:
- HTML: https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/starters.html#comp=9241
- JSON: https://www.digitalrock.de/egroupware/ranking/json.php?comp=9241&type=starters&debug=1
- XML:  https://www.digitalrock.de/egroupware/ranking/xml.php?comp=9241&type=starters

Only supported parameter is comp=<numeric competition id>.
You always need to add &type=starters, as otherwise you request a (not yet existing) result and get an error!

Data include usual competition data as eg. in calendar, plus categorys array containing following data for each category
of the competition with values for:
- GrpId numeric category id, eg. 5
- name of category, eg. "M E N  boulder"
- rkey unique string key of category, eg. ICC_FB
- url to full result page
An array with registered athletes containing beside usual athlete data:
- cat: numeric category id athlete is registered for
- reg_fed_id: numeric id of registering federation or nation for intl. competitions
- order: non-consecutive number representing the order of registration
An array with registering federations, if not an intl. competition, with following data:
- fed_id: numeric federation id
- name: name of federation
- shortcut: shortcut of federation

Athlete profile via XML JSON
============================

- HTML: https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/pstambl.html?person=6933&cat=6
- Template: view-source:https://www.digitalrock.de/egroupware/ranking/sitemgr/digitalrock/pstambl.html?person=6933&cat=6
- XML:  https://www.digitalrock.de/egroupware/ranking/xml.php?person=6933&cat=6
- JSON: https://www.digitalrock.de/egroupware/ranking/json.php?person=6933&cat=6

Required parameter is person (numeric athlete id) and optional cat (nummeric category id).
Optional category is required to display rank of athlete in current and older rankings of that category.

Data includes usual athlete data:
- firstname, lastname, nation, birthdate, age, birthplace, gender
- federation, fed_url: name and url of federation
- PerId, rkey: numerical and string id of climbing
- practice: practices climbing since "N years, since YYYY"
- other_sports: other sports practiced
- professional: professional climber or if not profession
- city, postcode, street: address data, usually only city is available
- photo, photo2: URL to portrait and action picture
- URLs: homepage, facebook, twitter, instagram, youtube, video_iframe
- last_comp: date in YYYY-mm-dd format of last competition
If category given:
- GrpId: nummerical id of category
- cat_name: name of category
- rankings: athletes rank in following rankings
  * 0: current (world) ranking
  * 1: current year (world) cup
  * 2: last year (world) cup
  * 3: two years ago (world) cup
  with following data:
  * rank, points: in ranking
  * date, name, url: of ranking
  * SerId: nummerical id of cup
Recorded results of athlete as array with following data per result:
- rank, date, name: of competition
- nation: for national results
- cat_name, GrpId: name and numeric id of category
- WetId: numeric id of competition
- url: of complete ranking

Javascript Profile widget supports HTML template to render the profile, see above example.
It also displays by default only N best results, with the option to show all results.

Search for athletes via XML / JSON
==================================

Example for searching atheletes with first or last name starting with "ralf":
- XML:  https://www.digitalrock.de/egroupware/ranking/xml.php?term=ralf
- JSON: https://www.digitalrock.de/egroupware/ranking/json.php?term=ralf

Returns array of objects with attributes "value" (PerId) and "label" like "BECKER Ralf (GER)".
Can be used directly with jQueryUI autocomplete.

If you have questions please ask.

Ralf
