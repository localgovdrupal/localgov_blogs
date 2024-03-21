<?php

namespace Drupal\Tests\localgov_blogs\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;

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
   * Test the contents list block.
   */
  public function testPrevNextBlock() {

    // Check empty when no pages.
    $channel = $this->createNode([
      'title' => 'Blog channel',
      'type' => 'localgov_blog_channel',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $this->drupalGet($channel->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Prev');
    $this->assertSession()->pageTextNotContains('Next');

    // Check navigation between 3 blog posts.
    $pages = [];
    for ($i = 1; $i <= 3; $i++) {
      $pages[] = $this->createNode([
        'title' => 'Blog post ' . $i,
        'type' => 'localgov_blog_post',
        'status' => NodeInterface::PUBLISHED,
        'localgov_blog_channel' => ['target_id' => $channel->id()],
      ]);
    }
    $this->drupalGet($pages[0]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Prev');
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($pages[1]->toUrl()->toString());
    $this->drupalGet($pages[1]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($pages[0]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($pages[2]->toUrl()->toString());
    $this->drupalGet($pages[2]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($pages[1]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Next');

    // Unpublish a page.
    $pages[1]->status = NodeInterface::NOT_PUBLISHED;
    $pages[1]->save();
    // Still linked.
    $content_admin = $this->drupalCreateUser(['bypass node access']);
    $this->drupalLogin($content_admin);
    $this->drupalGet($pages[0]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Prev');
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($pages[1]->toUrl()->toString());
    $this->drupalGet($pages[1]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($pages[0]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($pages[2]->toUrl()->toString());
    $this->drupalGet($pages[2]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($pages[1]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Next');
    $this->drupalLogout();
    // But not for anon.
    $this->drupalGet($pages[0]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Prev');
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($pages[2]->toUrl()->toString());
    $this->drupalGet($pages[2]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($pages[0]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Next');
    // Republish for next tests.
    $pages[1]->status = NodeInterface::PUBLISHED;
    $pages[1]->save();

    // Check deleting page.
    $pages[0]->delete();
    $this->drupalGet($pages[1]->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('Prev');
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($pages[2]->toUrl()->toString());
    $this->drupalGet($pages[2]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Prev');
    $this->assertSession()->responseContains($pages[1]->toUrl()->toString());
    $this->assertSession()->pageTextContains('Next');
    $this->assertSession()->responseContains($pages[2]->toUrl()->toString());
  }

}
