<?php

/**
 * @package CanadianBahaiLogin
 *
 * Plugin Name: Canadian Bahá'í Membership Portal Login
 * Plugin URI: https://sites.google.com/view/canadian-bahai-login/
 * Description: Integrates this website with the Membership Portal
 * Version: 0.0.1
 * Author: Glen Little
 * Author URI: https://sites.google.com/view/canadian-bahai-login/
 * Text Domain: canadian-bahai-login
 */

/*
Copyright 2023  Glen Little

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// register_activation_hook(__FILE__, 'add_role');
// function add_role()
// {
//   add_role(
//     'bahai',
//     __("Bahá'í", 'cdn-bahai-login'),
//     array(
//       'read' => true,
//     ),
//   );
// }

// Add the simple_role.
add_filter('query_vars', 'themeslug_query_vars');
function themeslug_query_vars($qvars)
{
  // what querystring params are we looking for?
  $qvars[] = 'act';
  $qvars[] = 'src';
  $qvars[] = 'sk';

  return $qvars;
}

add_action('template_redirect', 'my_plugin_callback');
function my_plugin_callback()
{
  // phpinfo();

  // src -- fixed        -- from this plugin
  // act -- fixed = auth -- from Portal
  $src = get_query_var('src');
  $act = get_query_var('act');
  echo 'TEST - ', $src, $act;

  // check if the current URL is the one that we want to respond to
  if ($src == 'cdnBahaiPortal' && $act == 'auth') {
    $sk = get_query_var('sk');
    echo 'Test2' . $sk;
    if (isset($sk) && $sk > '') {
      processIncomingAuthRequest($sk);
    }
  }
}

// receive the details
function processIncomingAuthRequest($sk)
{
  // get the user's temporary token from the query
  echo "SK: '{$sk}'";

  // our key - get from settings in this plugin
  $ck = 'DB434C66-FDF1-49CD-87A5-077F142C5AB1';

  // call to the Portal to get details
  $portalUrl = "https://portal.bahai.ca/CommAuthXmlM?ck={$ck}&sk={$sk}";

  echo $portalUrl;

  $response = file_get_contents($portalUrl);

  $xml = simplexml_load_string($response);
  $data = $xml->FieldData;

  $LoginStatus = $data->LoginStatus->__toString();

  if ($LoginStatus == 'Active') {

    $BahaiID = $data->BahaiID->__toString();
    $FirstName = $data->FirstName->__toString();
    $LastName = $data->LastName->__toString();
    $BirthDate = $data->BirthDate->__toString();
    $Gender = $data->Gender->__toString();
    $EmailAddress = $data->EmailAddress->__toString();
    $WithholdMail = $data->WithholdMail->__toString();
    $Screening = $data->Screening->__toString();
    $LSA_Field1 = $data->LSA_Field1->__toString();
    $LSA_Field2 = $data->LSA_Field2->__toString();
    $LSector = $data->LSector->__toString();
    $LNeighbourhood = $data->LNeighbourhood->__toString();

    // echo "Baha'i ID = {$xml->FieldData->BahaiID} for Canada";

    $user = get_user_by('email', $EmailAddress);

    $user_id = null;
    if (empty($user)) {
      // new user!

      // start with first last, chop at 50
      $username = substr(sanitize_user($FirstName . ' ' . $LastName), 0, 50);

      // add a number if needed
      $basename = $username;
      $suffix = 1;
      while (username_exists($username)) {
        $username = $basename . ' ' . $suffix++;
      }

      $new_user_array = array(
        'user_login' => $username,
        'user_pass'  => wp_generate_password(16, false),
        'user_email' => $EmailAddress,
        'role'       => 'subscriber',
        'first_name' => $FirstName,
        'last_name'  => $LastName,
        'show_admin_bar_front' => false,
      );

      $user_id = wp_insert_user($new_user_array);
    } else {
      // returning user
      $user_id = $user->ID;
      $username = $user->user_login;
    }

    $props = array();
    $props['ID'] = $user_id;
    foreach ($data->children() as $child) {
      // echo $child->getName() . '---' . $child . '<br>';
      $props['cdn_bahai_' . $child->getName()] = $child->__toString();
    }
    $result = wp_update_user($props);

    // if (is_wp_error($result)) {
    //   echo 'Error: ' . $result;
    // } else {
    //   // Success!
    //   echo 'User profile updated: ' . $result;
    // }

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true);

    // redirect back to same page without the querystring
    wp_redirect(strtok($_SERVER["REQUEST_URI"], '?'));
  } else {
    echo 'Not logged in: ' . $LoginStatus;
    // don't do anything...
  }
}

// add_action('wp_footer', 'footer');
// function footer()
// {
//   $when = date('h:i:s');
//   echo "I'm here and there at $when Test: $test";
// }
