<?php

namespace Drupal\Tests\localgov_blogs\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\node\NodeInterface;

/**
 * Tests user blocks.
 *
 * @group localgov_guides
 */
class PrevNextBlockTest extends BrowserTestBase {

  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'block',
    'path',
    'options',
    'localgov_blogs',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with the 'administer blocks' permission.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer blocks']);
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('localgov_blogs_prev_next_block');
    $this->drupalLogout();
  }

  /**
   * Test the Previous / Next Navigation block.
   */
  public function testPrevNextBlock() {

    // Create channel_one.
    $channel_one = $this->createNode([
      'title' => 'Blog channel_one',
      'type' => 'localgov_blog_channel_one',
      'status' => NodeInterface::PUBLISHED,
    ]);

    // Create channel_two.
    $channel_two = $this->createNode([
      'title' => 'Blog channel_two',
      'type' => 'localgov_blog_channel_two',
      'status' => NodeInterface::PUBLISHED,
    ]);

    // Create 6 posts.
    // Alternate them between channel one and two.
    $posts = [];
    for ($i = 1; $i <= 6; $i++) {
      $posts[$i] = $this->createNode([
        'title' => 'Blog post ' . $i,
        'type' => 'localgov_blog_post',
        'localgov_blog_date' => '2024-05-1' . $i,
        'status' => NodeInterface::PUBLISHED,
        'localgov_blog_channel' => ['target_id' => ($i % 2 !== 0 ? $channel_one->id() : $channel_two->id())],
      ]);
    }

    // Test Navigation.
    for ($i = 1; $i <= 6; $i++) {
      $this->drupalGet($posts[$i]->toUrl()->toString());

      // Post one and two will not have a prev link.
      if ($i <= 2) {
        $this->assertSession()->pageTextNotContains('Prev');
      }
      else {
        $this->assertSession()->pageTextContains('Prev');
      }

      // Post five and six will not have a next link.
      if ($i >= 5) {
        $this->assertSession()->pageTextNotContains('Next');
      }
      else {
        $this->assertSession()->pageTextContains('Next');
      }

      // Test the prev link is to the post two before, since posts will
      // alternate channels this tests the prev / next links target the correct
      // blog channel.
      $prev = $i - 2;
      if ($prev >= 1) {
        $this->assertSession()->responseContains($posts[$prev]->toUrl()->toString());
      }
      $next = $i + 2;
      if ($next <= 6) {
        $this->assertSession()->responseContains($posts[$next]->toUrl()->toString());
      }

    }
  }

  /**
   * Test the correct next / prev posts appear when all the same date.
   */
  public function testPrevNextWithSameDate() {

    // Create a channel.
    $channel = $this->createNode([
      'title' => 'Blog channel_one',
      'type' => 'localgov_blog_channel_one',
      'status' => NodeInterface::PUBLISHED,
    ]);

    // Create 6 posts, all on same date.
    $posts = [];
    for ($i = 1; $i <= 6; $i++) {
      $posts[$i] = $this->createNode([
        'title' => 'Blog post ' . $i,
        'type' => 'localgov_blog_post',
        'localgov_blog_date' => date('Y-m-d'),
        'status' => NodeInterface::PUBLISHED,
        'localgov_blog_channel' => ['target_id' => $channel->id()],
        'created' => strtotime('Midnight +' . $i . ' hour'),
        'changed' => strtotime('Midnight +' . $i . ' hour'),
      ]);
    }

    // Loop through each post to make sure next / prev has them in order.
    // This is done via xpath to test the correct link.
    for ($i = 1; $i <= 6; $i++) {
      $this->drupalGet($posts[$i]->toUrl()->toString());
      $prev = $i - 1;
      if ($prev >= 1) {
        $prev_query = $this->xpath('.//*[contains(concat(" ",normalize-space(@class)," ")," localgov-blog-navigation__previous ")]');
        $prev_link = $prev_query[0]->getAttribute('href');
        $this->assertEquals($posts[$prev]->toUrl()->toString(), $prev_link);
      }
      $next = $i + 1;
      if ($next <= 6) {
        $next_query = $this->xpath('.//*[contains(concat(" ",normalize-space(@class)," ")," localgov-blog-navigation__next ")]');
        $next_link = $next_query[0]->getAttribute('href');
        $this->assertEquals($posts[$next]->toUrl()->toString(), $next_link);
      }
    }
  }

}
