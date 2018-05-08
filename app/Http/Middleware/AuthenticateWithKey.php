<?php

namespace SportUrls\Http\Middleware;

use Closure;
use SportUrls\Classes\Model\User;

class AuthenticateWithKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        $key_valid = false;

        // grab the key
        if ($request->has('key')) {
          $api_key = $request->key;
          // check key length
          if (strlen($api_key) == 29) {
            // check to see if it matches
            $key_valid = true;
          }
        }

        if ($key_valid) {
          return $next($request);
        }
        else {
          return response()->json(array('error' => true, 'status_message' => "Invalid Api Key", 'status_code' => 401));
        }
    }
}
