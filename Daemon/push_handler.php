<?php

function push_error_handler( $error_handler, $error_types = 32767 )
{
  $old_callback = null;
 
  $callback = function( $errno, $errstr, $errfile, $errline, $errcontext )
  use ( &$old_callback, $error_handler )
  {
    $result = call_user_func($error_handler, $errno , $errstr, $errfile, $errline, $errcontext);
 
    if ( $result === false && $old_callback != null )
      return call_user_func( $old_callback, $errno , $errstr, $errfile, $errline, $errcontext );
 
    return $result;
  };
 
  $old_callback = set_error_handler($callback, $error_types);
}

