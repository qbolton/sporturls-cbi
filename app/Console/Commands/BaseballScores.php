<?php
namespace SportUrls\Console\Commands;

// set the default timezone
// date_default_timezone_set('America/New_York');
date_default_timezone_set('UTC');

use Illuminate\Console\Command;

use SportUrls\Classes\Fetch;
use SportUrls\Classes\Model\Crawlstat;
use SportUrls\Classes\Model\Scoreboard;
use \StdClass;

require('/var/www/html/sporturls/vendor/sunra/php-simple-html-dom-parser/Src/Sunra/PhpSimple/HtmlDomParser.php');

class BaseballScores extends Command
{
  /**
   * The name and signature of the console command.
   *
   * @var string
   */
  protected $signature = 'ncaa.baseball:scores {--url=none : A specific url to use when executing the script}';

  /**
   * The console command description.
   *
   * @var string
   */
  protected $description = 'This console command will gather NCAA division 1,2,3 baseball scores';

  /**
   * Create a new command instance.
   *
   * @return void
   */
  public function __construct()
  {
      parent::__construct();

      // crawlstat object
      $this->crawlstat = new Crawlstat();
      $this->crawlstat->name = 'NCAA-BaseballScores';

      // setup default urls
      $this->ncaaUrls = array(
        'd1' => 'http://www.ncaa.com/scoreboard/baseball/d1',
        'd2' => 'http://www.ncaa.com/scoreboard/baseball/d2',
        'd3' => 'http://www.ncaa.com/scoreboard/baseball/d3'
      );
  }

  /**
   * Execute the console command.
   *
   * @return mixed
   */
  public function handle()
  {
      $fetch_single_page = FALSE;

      $this->info("NCAA.BASEBALL:SCORES initializing on " . date(DATE_RFC2822) );
      // update crawlstats
      $this->crawlstat->status = 'RUNNING';
      $this->crawlstat->start_date = date('Y-m-d H:i:s');
      $this->crawlstat->save();

      // get options (url or [d1,d2,d3])
      $this->runUrl = $this->option('url');  //print_r($this->runUrl);

      // if url, then ignore other args and fetch url
      if (strcasecmp($this->runUrl,'none') != 0) {
        $this->info("Executing in single url mode");
        $fetch_single_page = TRUE;
      }
      else {
        $this->info("Executing in normal mode");
        $fetch_single_page = FALSE;
      }
   
      // Grab single page
      if ($fetch_single_page) {
        // execute function to grab specified page
        $this->info("Processing single url => {$this->runUrl}");
        
        // update the run_url
        $this->crawlstat->run_url = $this->runUrl;

        $page = $this->getUrl($this->runUrl); 
        // if url fetch was successful
        if ($page->success) {
          // also grab count, created, lang
          $html = $page->contents->query->results->result; //print_r($html);

          // try to parse the page
          $game_days = $this->parseGameData($html); //print_r($game_days);

          // if no game days the don't loop over anything
          if (!empty($game_days)) {
            // if we've got some games, try to save them
            foreach($game_days as $day => $games) {
              // directly loop over games
              foreach($games as $g) {
                $this->saveGameData($day,$g);
              }
            }
          }
          else {
            $this->error("Could not find any games on {$this->runUrl}");
            $this->crawlstat->message = "Could not find any games on {$this->runUrl}";
          }
        }
        else {
          $this->error("Could not fetch {$this->runUrl}");
          $this->error( print_r($page,TRUE) );

          // update crawlstats
          $this->crawlstat->status = 'ERROR_COMPLETE';
          $this->crawlstat->message = "Could not fetch {$this->runUrl}";
          $this->crawlstat->end_date = date('Y-m-d H:i:s');
          $this->crawlstat->save();

          // get out of program
          exit;
        }
      }
      // =========================================================
      // NOT IMPLEMENTED
      // if no specific runUrl, then just loop over the ncaaUrls
      // =========================================================
      /*else {
        foreach ($this->ncaaUrls as $division => $url) {
          $this->info("Processing {$division} => {$url}");
          $page = $this->getUrl($url); 
          // print_r($page); exit;

          // if url fetch was successful
          if ($page->success) {
            // also grab count, created, lang
            $html = $page->contents->query->results->result;

            // try to parse the page
            $games = $this->parseGameData($html);
          }
        }
      }*/
    $this->info("NCAA.BASEBALL:SCORES shutting down on " . date(DATE_RFC2822) );
    // update crawlstats
    $this->crawlstat->status = 'COMPLETE';
    $this->crawlstat->end_date = date('Y-m-d H:i:s');
    $this->crawlstat->save();
  }

  /**
   * Fetch page content
   *
   * @return string
   */
  private function getUrl($url) {
    //$full_text_query = 'use "http://www.datatables.org/data/htmlstring.xml" as htmlstring; select * from htmlstring where url="'. $url .'"';
    $full_text_query = 'use "http://www.sporturls.com/datatables/htmlstring.xml" as htmlstring; select * from htmlstring where url="'. $url .'"';
    $page = Fetch::withYQL($full_text_query); 
    return $page;
  }

  /**
   * Parse the string HTML document and returns an associative array of dates and the games on those days.
   *
   * @return mixed 
   */
  private function parseGameData($html) {
    $game_array = array();
    // create new DOM document
    $dom = \Sunra\PhpSimple\HtmlDomParser::str_get_html($html); 
    if ($dom === FALSE) {
      $this->warn('Could not get DOM from HTML string.  Trying the requested url directly');
      // Get html from url directly. if that fails then OH WELL.
      $dom = \Sunra\PhpSimple\HtmlDomParser::file_get_html($this->runUrl); 
      if ($dom === FALSE) {
        $this->warn('Complete fetch fail.  Could not load HTML from STRING; Could not load HTML from URL');
        return $game_array;
      }
    }
    
    // find the 'day-wrapper' sections of the html
    $day_sections = $dom->find('section.day'); 
   
    // if no day section, then try section and id=scoreboard...if no dice, then get out
    if (empty($day_sections)) { 
      $this->warn('Could not find section.day in HTML, looking for section#scoreboard');
      $day_sections = $dom->find('section#scoreboard');
      if (empty($day_sections)) {
        $this->warn('No known HTML sections containing game information available');
        return $game_array;
      }
    }
    

    // assuming there is a day section
    foreach($day_sections as $day) {
      // grap the day-wrapper
      $day_wrapper = $day->find('div.day-wrapper'); $game_date = strtotime($day_wrapper[0]->plaintext);
      // create game date array
      $game_array[$game_date] = array();

      // flow over the games
      $games = $day->find('section.game'); // print_r("Number of Games: " . count($games));
      // number of scheduled games
      $this->crawlstat->expected_games = count($games);

      foreach($games as $game) {
        // create new game array
        $g = array();

        // game status
        $g['game_status'] = $game->find('div.game-status',0)->plaintext;

        // if game status is empty or does not have the word 'Final' in it then skip
        if ( (strlen($g['game_status']) > 0) && ( stristr($g['game_status'],'final') !== FALSE ) ) {

          // try to grab additional innings off of the final status
          // preg_match('/\(?P<digit>\d+\)/',$g['game_status'],$matches);
          // $g['game_innings'] = $matches;

          $g['game_championship'] = $game->find('div.game-championship',0)->plaintext;

          // grab the full url for the game
          $g['url'] = 'http://www.ncaa.com' . $game->find('ul.linklist li a',0)->href;

          // grab the NCAA division off of the url
          $game_division = explode('/',$g['url']); 
          $g['game_division'] = trim($game_division[5]);

          // get team 1 RHE
          $team1 = $game->find('tr',1); //print_r($team1);

          // put controls around this to protect against games that were not played
          $g['team1_name'] = $team1->find('td.school div.team a',0)->title;
          $g['team1_runs'] = $team1->find('td.rhe',0)->plaintext;
          $g['team1_hits'] = $team1->find('td.rhe',1)->plaintext;
          $g['team1_errors'] = $team1->find('td.rhe',2)->plaintext;

          // get team 2 RHE
          $team2 = $game->find('tr',2);
          $g['team2_name'] = $team2->find('td.school div.team a',0)->title;
          $g['team2_runs'] = $team2->find('td.rhe',0)->plaintext;
          $g['team2_hits'] = $team2->find('td.rhe',1)->plaintext;
          $g['team2_errors'] = $team2->find('td.rhe',2)->plaintext;

          // game conference
          $attributes = explode(' ',preg_replace('/\s+/',' ',$game->class)); $attribute_count = count($attributes);
          // the first 4 array slots (0-3) can be ignored 
          $g['team2_conference'] = trim( $attributes[4] );

          // covers an attribute line that looks like this:
          // game final allconf all-conf western-athletic 
          if ($attribute_count == 5) {
            $g['team1_conference'] = $g['team2_conference'];
          }

          // covers an attribute line that looks like this:
          // game final allconf all-conf western-athletic alpha
          // game final allconf all-conf western-athletic omega
          // game final allconf all-conf western-athletic mountain-west 
          if ($attribute_count == 6) {
            if ( (strcasecmp($attributes[5],'alpha') == 0) || 
                 (strcasecmp($attributes[5],'omega') == 0) ) {
               // both teams in game are from same conference
               $g['team1_conference'] = $g['team2_conference'];
            }
            else {
              $g['team1_conference'] = trim( $attributes[5] );
            }
          }

          // covers an attribute line that looks like this:
          // game final allconf all-conf western-athletic mountain-west alpha
          // game final allconf all-conf western-athletic mountain-west omega
          if ($attribute_count == 7) { 
            $g['team1_conference'] = trim( $attributes[5] );  
          }

          // add game hash
          $g['game_hash'] = md5( trim($g['url']) );

          //print_r($g); 

          // push game onto array
          array_push($game_array[$game_date],$g);
        }
      }
      //print_r($game_array);
    }

    // destroy the dom object
    $dom->__destruct();

    return $game_array;
  }

  /**
   * Save the fetch game data
   *
   * @return mixed 
   */
  private function saveGameData($day,$game) {
    // get ready to save scoreboard
    $scoreboard = new Scoreboard;

    // check existence
    $existing_game = Scoreboard::where('game_hash',$game['game_hash'])->first();

    // update
    if (!empty($existing_game)) {
      $existing_game->team1_name = $game['team1_name'];
      $existing_game->team1_runs = $game['team1_runs'];
      $existing_game->team1_hits = $game['team1_hits'];
      $existing_game->team1_errors = $game['team1_errors'];
      $existing_game->team1_conference = $game['team1_conference'];
      $existing_game->team2_name = $game['team2_name'];
      $existing_game->team2_runs = $game['team2_runs'];
      $existing_game->team2_hits = $game['team2_hits'];
      $existing_game->team2_errors = $game['team2_errors'];
      $existing_game->team2_conference = $game['team2_conference'];
      $existing_game->game_division = $game['game_division'];
      $existing_game->game_url = $game['url'];
      $existing_game->game_status = $game['game_status'];
      $existing_game->game_championship = $game['game_championship'];
      $existing_game->game_hash = $game['game_hash'];
      $existing_game->game_date_int = $day;
      $existing_game->game_date = date('Y-m-d',$day);
      $existing_game->last_updated = date('Y-m-d H:i:s');

      $this->info("Updating game {$existing_game->game_id}: {$game['team1_name']} VS {$game['team2_name']}");

      $existing_game->save(); $this->crawlstat->records_updated++;
    }
    // insert
    else {
      // set up the new record
      $scoreboard->team1_name = $game['team1_name'];
      $scoreboard->team1_runs = $game['team1_runs'];
      $scoreboard->team1_hits = $game['team1_hits'];
      $scoreboard->team1_errors = $game['team1_errors'];
      $scoreboard->team1_conference = $game['team1_conference'];
      $scoreboard->team2_name = $game['team2_name'];
      $scoreboard->team2_runs = $game['team2_runs'];
      $scoreboard->team2_hits = $game['team2_hits'];
      $scoreboard->team2_errors = $game['team2_errors'];
      $scoreboard->team2_conference = $game['team2_conference'];
      $scoreboard->game_division = $game['game_division'];
      $scoreboard->game_url = $game['url'];
      $scoreboard->game_status = $game['game_status'];
      $scoreboard->game_championship = $game['game_championship'];
      $scoreboard->game_hash = $game['game_hash'];
      $scoreboard->game_date_int = $day;
      $scoreboard->game_date = date('Y-m-d',$day);
      $scoreboard->last_updated = date('Y-m-d H:i:s');

      $scoreboard->save(); $this->crawlstat->records_inserted++;

      $this->info("Inserting game {$scoreboard->game_id}: {$game['team1_name']} VS {$game['team2_name']}");
    }
  }

}
