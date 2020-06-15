<?php
/**
 * @file
 * @author Tony Galligani
 * Contains \Drupal\mymutations\Controller\MyMutationsController.
 * Please place this file under your example(module_root_folder)/src/Controller/
 */
namespace Drupal\mymutations\Controller;
/**
 * Provides route responses for the Example module.
 */
class MyMutationsController {
  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function myPage() {
    $element = array(
      '#markup' => 'Hello world!',
    );
    return $element;
  }
}
?>