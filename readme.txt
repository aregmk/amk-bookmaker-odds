=== How to Use? ===
1. Download this repository and install as a standard WP plugin.
2. Download demo bookmakers.xml from https://github.com/aregmk/Bookmaker-Odds-Server/blob/main/bookmakers.xml
3. Import bookmakers through standard WP Import Tool: /wp-admin/import.php
4. Go to any post/page, edit and add "AMK Bookmaker Odds" block.
5. Edit block settings from right sidebar:
   a) Add scraping url: https://aregmk.github.io/Bookmaker-Odds-Server/Oddschecker.html
   b) Set which bookmakers you want to show on frontend
6. Save the page and check the results.


=== Limitations ===
It is extremely hard to scrape data from big players like oddschecker.com or oddsportal.com.
Not only they often change their css classes by setting not human-friendly names to make it impossible
to do correct and reliable xpath queries for scraping, but also these sites render their important
content with React on frontend, so it is impossible to scrape with PHP requests.

=== My temporary solution in the scope of the task ===
I have decided to manually fetch an example page from oddschecker.com and host it in github.
So now I can scrape the data from that page.
I know it is not a live data, but I had to find a quick solution to showcase my plugin.

=== Notes ===
I decided to use WP native features in many cases, for example, I created Bookmaker CPT
to store bookmaker data, so the admin can easily add/remove/disable any bookmaker through
WP native, intuitive and user-friendly interface.
Bookmaker posts have metadata which is used when we render the "AMK Bookmaker Odds" block.
Bookmakers can be enabled/disabled on block level.
