<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::group(['prefix' => 'api/v1'], function () {

  // ===============================================
  // API Route To Pull Scoreboards Last Update Date Time
  // ===============================================
  Route::get('games/lastupdate',function() {
    //print_r(Request::all());
    $row = DB::table('scoreboards')->max('last_updated'); 
    return Response::json(array(
          'error' => false,
          'last_updated' => strtotime($row),
          'status_message' => "Games last updated on " . $row,
          'status_code' => 200
          ));
  });

  // ===============================================
  // API Route To Pull All Data
  // ===============================================
  Route::get('games/all',function() {
    // grab all of the current rows
    $games = SportUrls\Classes\Model\Scoreboard::all(
      array('game_id','team1_name','team1_runs','team2_name','team2_runs','team1_conference','team2_conference','game_status','game_date')
    );
    if (count($games) == 0) {
      $message = 'No Games Found';
    }
    else {
      $message = null;
    }
    return Response::json(array(
          'error' => false,
          'count' => count($games),
          'games' => $games,
          'status_message' => $message,
          'status_code' => 200
          ));
  });

  // ===============================================
  // API Route To Pull All Data By Date
  // ===============================================
  Route::get('games/{date?}',function($day = null) {

    // if null then grab just the data for the current day
    if (is_null($day)) {
      $day_int = strtotime( date('Y-m-d 00:00:00') );
    }
    // if it's an integer
    else if (is_numeric($day)) {
      // have code to remove anything that ain't a digit
      $day_int = $day;
    }
    else {
      $day_int = strtotime( date('Y-m-d 00:00:00',strtotime($day)) ); //print_r($day_int);
    }

    // if the request specifies pulling a single day
    if ((Request::has('strict')) && 
       (strcasecmp(Request::input('strict'),'yes') == 0) ) {
      $games = SportUrls\Classes\Model\Scoreboard::where('game_date_int','=',$day_int)->get(
        array('game_id','team1_name','team1_runs','team2_name','team2_runs','team1_conference','team2_conference','game_status','game_date')
      );
    }
    else if ((Request::has('strict')) && 
       (strcasecmp(Request::input('strict'),'no') == 0) ) {
      $games = SportUrls\Classes\Model\Scoreboard::where('game_date_int','>=',$day_int)->get(
        array('game_id','team1_name','team1_runs','team2_name','team2_runs','team1_conference','team2_conference','game_status','game_date')
      );
    }
    else {
      $games = SportUrls\Classes\Model\Scoreboard::where('game_date_int','>=',$day_int)->get(
        array('game_id','team1_name','team1_runs','team2_name','team2_runs','team1_conference','team2_conference','game_status','game_date')
      );
    }

    if (count($games) == 0) {
      $message = 'No Games Found For Specified Date';
    }
    else {
      $message = null;
    }
      
    // grab all of the current rows
    return Response::json(array(
          'error' => false,
          'count' => count($games),
          'games' => $games,
          'status_message' => $message,
          'status_code' => 200
          ));
  });

});

// ######################################################################
// ROUTE GROUPS FOR http://api.sporturls.com/v1/college/baseball
// ######################################################################
Route::group(['prefix' => 'v1/college/baseball'], function () {

  // ===============================================
  // API Route To Pull Scoreboards Last Update Date Time
  // ===============================================
  Route::get('games/lastupdate',function() {
    $row = DB::table('scoreboards')->max('last_updated'); 
    return Response::json(array(
          'error' => false,
          'last_updated' => strtotime($row),
          'status_message' => "Games last updated on " . $row,
          'status_code' => 200
          ));
  })->middleware(['auth.key']);

  // ===============================================
  // API Route To Pull All Data
  // ===============================================
  Route::get('games/all',function() {
    // grab all of the current rows
    $games = SportUrls\Classes\Model\Scoreboard::all(
      array('game_id','team1_name','team1_runs','team2_name','team2_runs','team1_conference','team2_conference','game_status','game_date')
    );
    if (count($games) == 0) {
      $message = 'No Games Found';
    }
    else {
      $message = null;
    }
    return Response::json(array(
          'error' => false,
          'count' => count($games),
          'games' => $games,
          'status_message' => $message,
          'status_code' => 200
          ));
  });

  // ===============================================
  // API Route To Pull Game Data By Division
  // ===============================================

  // ===============================================
  // API Route To Pull All Data By Date
  // ===============================================
  Route::get('games/{date?}',function($day = null) {

    // if null then grab just the data for the current day
    if (is_null($day)) {
      $day_int = strtotime( date('Y-m-d 00:00:00') );
    }
    // if it's an integer
    else if (is_numeric($day)) {
      // have code to remove anything that ain't a digit
      $day_int = $day;
    }
    else {
      $day_int = strtotime( date('Y-m-d 00:00:00',strtotime($day)) ); //print_r($day_int);
    }

    $games = SportUrls\Classes\Model\Scoreboard::where('game_date_int','>=',$day_int)->get(
      array('game_id','team1_name','team1_runs','team2_name','team2_runs','team1_conference','team2_conference','game_status','game_date')
    );

    if (count($games) == 0) {
      $message = 'No Games Found For Specified Date';
    }
    else {
      $message = null;
    }
      
    // grab all of the current rows
    return Response::json(array(
          'error' => false,
          'count' => count($games),
          'games' => $games,
          'status_message' => $message,
          'status_code' => 200
          ));
  });

});
