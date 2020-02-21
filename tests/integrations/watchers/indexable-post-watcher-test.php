<?php

namespace Yoast\WP\SEO\Tests\Integrations\Watchers;

use Brain\Monkey;
use Mockery;
use Yoast\WP\SEO\Builders\Indexable_Builder;
use Yoast\WP\SEO\Conditionals\Migrations_Conditional;
use Yoast\WP\SEO\Helpers\Author_Archive_Helper;
use Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher;
use Yoast\WP\SEO\Repositories\Indexable_Hierarchy_Repository;
use Yoast\WP\SEO\Repositories\Indexable_Repository;
use Yoast\WP\SEO\Tests\Doubles\Indexable_Post_Watcher_Double;
use Yoast\WP\SEO\Tests\Mocks\Indexable;
use Yoast\WP\SEO\Tests\TestCase;

/**
 * Class Indexable_Post_Test.
 *
 * @group indexables
 * @group watchers
 *
 * @coversDefaultClass \Yoast\WP\SEO\Integrations\Watchers\Indexable_Post_Watcher
 * @covers ::<!public>
 *
 * @package Yoast\Tests\Watchers
 */
class Indexable_Post_Watcher_Test extends TestCase {

	/**
	 * Represents the indexable repository.
	 * @var Mockery\MockInterface|Indexable_Repository
	 */
	private $repository;

	/**
	 * Represents the indexable builder.
	 *
	 * @var Mockery\MockInterface|Indexable_Builder
	 */
	private $builder;

	/**
	 * Represents the hierarchy repository.
	 *
	 * @var Mockery\MockInterface|Indexable_Hierarchy_Repository
	 */
	private $hierarchy_repository;

	/**
	 * The author archive helper mock.
	 *
	 * @var Mockery\MockInterface|Author_Archive_Helper
	 */
	private $author_archive;

	/**
	 * Represents the class we are testing.
	 *
	 * @var Indexable_Post_Watcher_Double
	 */
	private $instance;

	/**
	 * Initializes the test mocks.
	 */
	public function setUp() {
		$this->repository           = Mockery::mock( Indexable_Repository::class );
		$this->builder              = Mockery::mock( Indexable_Builder::class );
		$this->hierarchy_repository = Mockery::mock( Indexable_Hierarchy_Repository::class );
		$this->author_archive       = Mockery::mock( Author_Archive_Helper::class );
		$this->instance             = Mockery::mock(
			Indexable_Post_Watcher_Double::class,
			[
				$this->repository,
				$this->builder,
				$this->hierarchy_repository,
				$this->author_archive,
			]
		)
			->makePartial()
			->shouldAllowMockingProtectedMethods();

		return parent::setUp();
	}

	/**
	 * Tests if the expected conditionals are in place.
	 *
	 * @covers ::get_conditionals
	 */
	public function test_get_conditionals() {
		$this->assertEquals(
			[ Migrations_Conditional::class ],
			Indexable_Post_Watcher::get_conditionals()
		);
	}

	/**
	 * Tests if the expected hooks are registered.
	 *
	 * @covers ::__construct
	 * @covers ::register_hooks
	 */
	public function test_register_hooks() {
		$this->instance->register_hooks();

		$this->assertNotFalse( \has_action( 'wp_insert_post', [ $this->instance, 'build_indexable' ] ) );
		$this->assertNotFalse( \has_action( 'delete_post', [ $this->instance, 'delete_indexable' ] ) );
	}

	/**
	 * Tests if the indexable is being deleted.
	 *
	 * @covers ::delete_indexable
	 */
	public function test_delete_indexable() {
		$id   = 1;
		$post = (object) [
			'post_author' => 0,
			'post_type'   => 'post',
			'ID'          => 0,
		];

		$indexable = Mockery::mock();
		$indexable->id = 1;
		$indexable->is_public = true;
		$indexable->object_type = 'post';
		$indexable->expects( 'delete' )->once();

		$this->repository->expects( 'find_by_id_and_type' )->once()->with( $id, 'post', false )->andReturn( $indexable );
		$this->hierarchy_repository->expects( 'clear_ancestors' )->once()->with( $id )->andReturn( true );

		Monkey\Functions\expect( 'get_post' )->once()->with( $id )->andReturn( $post );

		$this->instance
			->expects( 'update_relations' )
			->with( $post )
			->once();

		$this->instance
			->expects( 'update_has_public_posts' )
			->with( $indexable )
			->once();

		$this->instance->delete_indexable( $id );
	}

	/**
	 * Tests if the indexable is being deleted.
	 *
	 * @covers ::delete_indexable
	 */
	public function test_delete_indexable_does_not_exist() {
		$id = 1;

		$this->repository->expects( 'find_by_id_and_type' )->once()->with( $id, 'post', false )->andReturn( false );

		$this->instance->delete_indexable( $id );
	}

	/**
	 * Tests the save meta functionality.
	 *
	 * @covers ::build_indexable
	 * @covers ::is_post_indexable
	 */
	public function test_build_indexable() {
		$id = 1;

		Monkey\Functions\expect( 'wp_is_post_revision' )->once()->with( $id )->andReturn( false );
		Monkey\Functions\expect( 'wp_is_post_autosave' )->once()->with( $id )->andReturn( false );

		$indexable_mock = Mockery::mock( Indexable::class );
		$indexable_mock->expects( 'save' )->once();

		$this->repository->expects( 'find_by_id_and_type' )->once()->with( $id, 'post', false )->andReturn( $indexable_mock );
		$this->repository->expects( 'create_for_id_and_type' )->never();
		$this->builder->expects( 'build_for_id_and_type' )->once()->with( $id, 'post', $indexable_mock )->andReturn( $indexable_mock );

		$this->instance->build_indexable( $id );
	}

	/**
	 * Tests the early return for non-indexable post.
	 *
	 * @covers ::build_indexable
	 */
	public function test_build_indexable_is_post_revision() {
		$id = 1;

		Monkey\Functions\expect( 'wp_is_post_revision' )->once()->with( $id )->andReturn( true );

		$this->instance->build_indexable( $id );
	}

	/**
	 * Tests the early return for non-indexable post.
	 *
	 * @covers ::build_indexable
	 */
	public function test_build_indexable_is_post_autosave() {
		$id = 1;

		Monkey\Functions\expect( 'wp_is_post_revision' )->once()->with( $id )->andReturn( false );
		Monkey\Functions\expect( 'wp_is_post_autosave' )->once()->with( $id )->andReturn( true );

		$this->instance->build_indexable( $id );
	}

	/**
	 * Tests the build indexable functionality for a multisite that has been switched.
	 *
	 * @covers ::build_indexable
	 * @covers ::is_multisite_and_switched
	 */
	public function test_build_indexable_is_multisite_and_switched() {
		$id = 1;

		$this->instance
			->expects( 'is_multisite_and_switched' )
			->once()
			->andReturnTrue();

		$this->repository
			->expects( 'find_by_id_and_type' )
			->never()
			->with( $id, 'post', false );

		$this->instance->build_indexable( $id );
	}

	/**
	 * Tests the build_indexable functionality with an exception being thrown.
	 *
	 * @covers ::build_indexable
	 */
	public function test_build_indexable_with_thrown_exception() {
		$id = 1;

		$this->instance
			->expects( 'is_multisite_and_switched' )
			->once()
			->andReturnFalse();

		$this->instance
			->expects( 'is_post_indexable' )
			->with( 1 )
			->once()
			->andReturnTrue();

		$indexable_mock = Mockery::mock( Indexable::class );
		$indexable_mock->expects( 'save' )->never();

		$this->repository->expects( 'find_by_id_and_type' )->once()->with( $id, 'post', false )->andThrow( \Exception::class );

		$this->instance->build_indexable( $id );
	}

	/**
	 * Tests the save meta functionality.
	 *
	 * @covers ::build_indexable
	 */
	public function test_build_indexable_does_not_exist() {
		$id = 1;

		Monkey\Functions\expect( 'wp_is_post_revision' )->once()->with( $id )->andReturn( false );
		Monkey\Functions\expect( 'wp_is_post_autosave' )->once()->with( $id )->andReturn( false );

		$indexable_mock = Mockery::mock( Indexable::class );
		$indexable_mock->expects( 'save' )->once();

		$this->repository->expects( 'find_by_id_and_type' )->once()->with( $id, 'post', false )->andReturn( false );
		$this->builder->expects( 'build_for_id_and_type' )->once()->with( $id, 'post', false )->andReturn( $indexable_mock );

		$this->instance->build_indexable( $id );
	}

	/**
	 * Tests the updated where the indexable isn't for a post.
	 *
	 * @covers ::updated_indexable
	 */
	public function test_updated_indexable_non_post() {
		$this->instance
			->expects( 'update_relations' )
			->never();

		$old_indexable     = Mockery::mock();
		$updated_indexable = Mockery::mock();
		$updated_indexable->object_type = 'term';

		$this->instance->updated_indexable( $updated_indexable, $old_indexable );
	}

	/**
	 * Tests the updated indexable method with no transition in status.
	 *
	 * @covers ::updated_indexable
	 */
	public function test_updated_indexable_not_public_and_no_transition() {
		$this->instance
			->expects( 'update_relations' )
			->never();

		$old_indexable            = Mockery::mock();
		$old_indexable->is_public = false;

		$updated_indexable              = Mockery::mock();
		$updated_indexable->object_type = 'post';
		$updated_indexable->is_public   = false;

		$this->instance
			->expects( 'update_has_public_posts' )
			->with( $updated_indexable )
			->once();

		$this->instance->updated_indexable( $updated_indexable, $old_indexable );
	}

	/**
	 * Tests the updated indexable method with a transition in status.
	 *
	 * @covers ::updated_indexable
	 */
	public function test_updated_indexable_public_transition() {
		$this->instance
			->expects( 'update_relations' )
			->with( [] )
			->once();

		$old_indexable            = Mockery::mock();
		$old_indexable->is_public = true;

		$updated_indexable              = Mockery::mock();
		$updated_indexable->object_type = 'post';
		$updated_indexable->is_public   = false;
		$updated_indexable->object_id   = 1;

		Monkey\Functions\expect( 'get_post' )
			->once()
			->with( 1 )->andReturn( [] );

		$this->instance
			->expects( 'update_has_public_posts' )
			->with( $updated_indexable )
			->once();

		$this->instance->updated_indexable( $updated_indexable, $old_indexable );
	}

	/**
	 * Tests that update_has_public_posts updates the author archive too.
	 *
	 * @covers ::update_has_public_posts
	 */
	public function test_update_has_public_posts_with_post() {
		$post_indexable                  = Mockery::mock();
		$post_indexable->object_sub_type = 'post';
		$post_indexable->author_id       = 1;

		$author_indexable            = Mockery::mock();
		$author_indexable->object_id = 11;

		$this->repository->expects( 'find_by_id_and_type' )->with( 1, 'user' )->once()->andReturn( $author_indexable );
		$this->author_archive->expects( 'author_has_public_posts' )->with( 11 )->once()->andReturn( true );
		$author_indexable->expects( 'save' )->once();

		$this->instance->update_has_public_posts( $post_indexable );

		$this->assertTrue( $author_indexable->has_public_posts );
	}

	/**
	 * Tests that update_has_public_posts updates the author archive .
	 *
	 * @covers ::update_has_public_posts
	 */
	public function test_update_has_public_posts_with_post_throwing_exceptions() {
		$post_indexable                  = Mockery::mock();
		$post_indexable->object_sub_type = 'post';
		$post_indexable->author_id       = 1;

		$this->repository->expects( 'find_by_id_and_type' )->with( 1, 'user' )->once()->andThrow( new \Exception() );
		$this->author_archive->expects( 'author_has_public_posts' )->never();

		$this->instance->update_has_public_posts( $post_indexable );
	}

	/**
	 * Tests the attachment logic with no attachment (post) found.
	 *
	 * @covers ::update_has_public_posts
	 */
	public function test_update_has_public_posts_for_attachment_with_no_post_found() {
		$indexable                  = Mockery::mock( Indexable::class );
		$indexable->object_id       = 1337;
		$indexable->object_sub_type = 'attachment';

		$indexable->expects( 'save' )->never();

		Monkey\Functions\expect( 'get_post' )
			->once()
			->with( 1337 )
			->andReturn( false );

		$this->instance->update_has_public_posts( $indexable );
	}

	/**
	 * Tests the attachment logic with the attachment not having a post parent.
	 *
	 * @covers ::update_has_public_posts
	 */
	public function test_update_has_public_posts_for_attachment_with_no_post_parent() {
		$indexable                  = Mockery::mock( Indexable::class );
		$indexable->object_id       = 1337;
		$indexable->object_sub_type = 'attachment';

		$indexable->expects( 'save' )->once();

		Monkey\Functions\expect( 'get_post' )
			->once()
			->with( 1337 )
			->andReturn(
				(object) [
					'post_parent' => 0,
				]
			);

		$this->instance
			->expects( 'attachment_has_public_posts' )
			->with( 0, $indexable )
			->once()
			->andReturnFalse();

		$this->instance->update_has_public_posts( $indexable );

		$this->assertFalse( $indexable->has_public_posts );
	}

	/**
	 * Tests the attachment logic with the attachment not having a post parent.
	 *
	 * @covers ::update_has_public_posts
	 */
	public function test_update_has_public_posts_for_attachment_with_no_value_change() {
		$indexable                   = Mockery::mock( Indexable::class );
		$indexable->object_id        = 1337;
		$indexable->object_sub_type  = 'attachment';
		$indexable->has_public_posts = true;

		$indexable->expects( 'save' )->never();

		Monkey\Functions\expect( 'get_post' )
			->once()
			->with( 1337 )
			->andReturn(
				(object) [
					'post_parent' => 707,
				]
			);

		$this->instance
			->expects( 'attachment_has_public_posts' )
			->with( 707, $indexable )
			->once()
			->andReturnTrue();

		$this->instance->update_has_public_posts( $indexable );
	}

	/**
	 * Tests if the attachment has public posts when no parent id is set.
	 *
	 * @covers ::attachment_has_public_posts
	 */
	public function test_attachment_has_public_posts_with_no_parent_id() {
		$this->assertFalse( $this->instance->attachment_has_public_posts( 0, Mockery::mock( Indexable::class ) ) );
	}

	/**
	 * Tests if the attachment has public posts with having publish as the attachment post status.
	 *
	 * @covers ::attachment_has_public_posts
	 */
	public function test_attachment_has_public_posts_with_no_inherit_post_status() {
		$indexable = Mockery::mock( Indexable::class );
		$indexable->post_status = 'publish';

		$this->assertFalse( $this->instance->attachment_has_public_posts( 1337, $indexable ) );
	}

	/**
	 * Tests if the attachment has public posts with having publish as the attachment post status.
	 *
	 * @covers ::attachment_has_public_posts
	 */
	public function test_attachment_has_public_posts_with_public_parent_indexable() {
		$indexable = Mockery::mock( Indexable::class );
		$indexable->post_status = 'inherit';

		$parent_indexable = Mockery::mock( Indexable::class );
		$parent_indexable->is_public = true;

		$this->repository
			->expects( 'find_by_id_and_type' )
			->once()
			->with( 1337, 'post' )
			->andReturn( $parent_indexable );

		$this->assertTrue( $this->instance->attachment_has_public_posts( 1337, $indexable ) );
	}

	/**
	 * Tests if the attachment has public posts with the repository throwing an exception.
	 *
	 * @covers ::attachment_has_public_posts
	 */
	public function test_attachment_has_public_posts_with_exception_being_thrown() {
		$indexable = Mockery::mock( Indexable::class );
		$indexable->post_status = 'inherit';

		$this->repository
			->expects( 'find_by_id_and_type' )
			->once()
			->with( 1337, 'post' )
			->andThrow( \Exception::class );

		$this->assertFalse( $this->instance->attachment_has_public_posts( 1337, $indexable ) );
	}

	/**
	 * Tests the routine for updating the relations.
	 *
	 * @covers ::update_relations
	 */
	public function test_update_relations() {
		$post = (object) [
			'post_author' => 1,
			'post_type'   => 'post',
			'ID'          => 1,
		];

		$indexable = Mockery::mock( Indexable::class );
		$indexable->is_public = true;
		$indexable->expects( 'save' )->once();

		$this->instance
			->expects( 'get_related_indexables' )
			->once()
			->with( $post )
			->andReturn( [ $indexable ] ) ;

		$this->instance->update_relations( $post );
	}

	/**
	 * Tests the routine for updating the relations.
	 *
	 * @covers ::update_relations
	 */
	public function test_update_relations_with_a_non_public_indexable() {
		$post = (object) [
			'post_author' => 1,
			'post_type'   => 'post',
			'ID'          => 1,
		];

		$indexable = Mockery::mock( Indexable::class );
		$indexable->is_public = false;
		$indexable->expects( 'save' )->never();

		$this->instance
			->expects( 'get_related_indexables' )
			->once()
			->with( $post )
			->andReturn( [ $indexable ] ) ;

		$this->instance->update_relations( $post );
	}

	/**
	 * Tests the routine for updating the relations with no related indexables found.
	 *
	 * @covers ::update_relations
	 */
	public function test_update_relations_with_no_indexables_found() {
		$post = (object) [
			'post_author' => 1,
			'post_type'   => 'post',
			'ID'          => 1,
		];

		$this->instance
			->expects( 'get_related_indexables' )
			->once()
			->with( $post )
			->andReturn( [] ) ;

		$this->instance->update_relations( $post );
	}

	/**
	 * Tests the retrieval of the related indexables for a post.
	 *
	 * @covers ::get_related_indexables
	 */
	public function test_get_releated_indexables() {
		$post = (object) [
			'post_author' => 1,
			'post_type'   => 'post',
			'ID'          => 1,
		];

		Monkey\Functions\expect( 'get_post_taxonomies' )
			->once()
			->with( 1 )
			->andReturn(
				[
					'taxonomy',
					'another-taxonomy'
				]
			);

		Monkey\Functions\expect( 'is_taxonomy_viewable' )
			->once()
			->with( 'taxonomy' )
			->andReturn( true );

		Monkey\Functions\expect( 'is_taxonomy_viewable' )
			->once()
			->with( 'another-taxonomy' )
			->andReturn( true );

		Monkey\Functions\expect( 'get_the_terms' )
			->once()
			->with( 1, 'taxonomy' )
			->andReturn(
				[
					(object) [
						'term_id' => 1337,
					],
					(object) [
						'term_id' => 1414,
					],
				]
			);

		Monkey\Functions\expect( 'get_the_terms' )
			->once()
			->with( 1, 'another-taxonomy' )
			->andReturnNull();

		$indexable = Mockery::mock( Indexable::class );

		$this->repository
			->expects( 'find_by_id_and_type' )
			->once()
			->with( 1, 'user', false )
			->andReturn( $indexable );

		$this->repository
			->expects( 'find_for_post_type_archive' )
			->once()
			->with( 'post', false )
			->andReturn( $indexable );

		$this->repository
			->expects( 'find_for_home_page' )
			->once()
			->with( false )
			->andReturn( $indexable );

		$this->repository
			->expects( 'find_by_id_and_type' )
			->once()
			->with( 1337, 'term', false )
			->andReturn( $indexable );

		$this->repository
			->expects( 'find_by_id_and_type' )
			->once()
			->with( 1414, 'term', false )
			->andReturnNull();

		$this->assertEquals(
			[ $indexable, $indexable, $indexable, $indexable ],
			$this->instance->get_related_indexables( $post )
		);
	}

}
