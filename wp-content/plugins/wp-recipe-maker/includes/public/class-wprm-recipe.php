<?php
/**
 * Represents a recipe.
 *
 * @link       http://bootstrapped.ventures
 * @since      1.0.0
 *
 * @package    WP_Recipe_Maker
 * @subpackage WP_Recipe_Maker/includes/public
 */

/**
 * Represents a recipe.
 *
 * @since      1.0.0
 * @package    WP_Recipe_Maker
 * @subpackage WP_Recipe_Maker/includes/public
 * @author     Brecht Vandersmissen <brecht@bootstrapped.ventures>
 */
class WPRM_Recipe {

	/**
	 * WP_Post object associated with this recipe post type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      object    $post    WP_Post object of this recipe post type.
	 */
	private $post;

	/**
	 * Metadata associated with this recipe post type.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array    $meta    Recipe metadata.
	 */
	private $meta = false;

	/**
	 * Get new recipe object from associated post.
	 *
	 * @since    1.0.0
	 * @param		 object $post WP_Post object for this recipe post type.
	 */
	public function __construct( $post ) {
		$this->post = $post;
	}

	/**
	 * Get recipe data.
	 *
	 * @since    1.0.0
	 */
	public function get_data() {
		$recipe = array();

		// Technical Fields.
		$recipe['id'] = $this->id();

		// Recipe Details.
		$recipe['image_id'] = $this->image_id();
		$recipe['image_url'] = $this->image_url();
		$recipe['name'] = $this->name();
		$recipe['summary'] = $this->summary();

		$recipe['author_display'] = $this->author_display( true );
		$recipe['author_name'] = $this->custom_author_name();
		$recipe['author_link'] = $this->custom_author_link();
		$recipe['servings'] = $this->servings();
		$recipe['servings_unit'] = $this->servings_unit();
		$recipe['prep_time'] = $this->prep_time();
		$recipe['cook_time'] = $this->cook_time();
		$recipe['total_time'] = $this->total_time();

		$recipe['tags'] = array();
		$taxonomies = WPRM_Taxonomies::get_taxonomies();
		foreach ( $taxonomies as $taxonomy => $options ) {
			$key = substr( $taxonomy, 5 ); // Get rid of wprm_.
			$recipe['tags'][ $key ] = $this->tags( $key );
		}

		// Ingredients & Instructions.
		$recipe['ingredients'] = $this->ingredients();
		$recipe['instructions'] = $this->instructions();

		// Recipe Notes.
		$recipe['notes'] = $this->notes();

		// Recipe Nutrition.
		$recipe['nutrition'] = $this->nutrition();

		// Other fields.
		$recipe['ingredient_links_type'] = $this->ingredient_links_type();

		return $recipe;
	}

	/**
	 * Get metadata value.
	 *
	 * @since    1.0.0
	 * @param		 mixed $field		Metadata field to retrieve.
	 * @param		 mixed $default	Default to return if metadata is not set.
	 */
	public function meta( $field, $default ) {
		if ( ! $this->meta ) {
			$this->meta = get_post_custom( $this->id() );
		}

		if ( isset( $this->meta[ $field ] ) ) {
			return $this->meta[ $field ][0];
		}

		return $default;
	}

	/**
	 * Try to unserialize as best as possible.
	 *
	 * @since    1.22.0
	 * @param	 mixed $maybe_serialized Potentially serialized data.
	 */
	public function unserialize( $maybe_serialized ) {
		$unserialized = @maybe_unserialize( $maybe_serialized );

		if ( false === $unserialized ) {
			$unserialized = unserialize( preg_replace_callback( '!s:(\d+):"(.*?)";!', array( $this, 'regex_replace_serialize' ), $maybe_serialized ) );
		}

		return $unserialized;
	}

	/**
	 * Callback for regex to fix serialize issues.
	 *
	 * @since    1.20.0
	 * @param	 mixed $match Regex match.
	 */
	public function regex_replace_serialize( $match ) {
		return ( $match[1] == strlen( $match[2] ) ) ? $match[0] : 's:' . strlen( $match[2] ) . ':"' . $match[2] . '";';
	}

	/**
	 * Get the recipe author.
	 *
	 * @since    1.0.0
	 */
	public function author() {
		switch ( $this->author_display() ) {
			case 'post_author':
				return $this->post_author_name();
			case 'custom':
				$link = $this->custom_author_link();
				$name = $this->custom_author_name();

				if ( $link && $name && WPRM_Addons::is_active( 'premium' ) ) {
					return '<a href="' . esc_attr( $link ) . '" target="_blank">' . $name . '</a>';
				} else {
					return $name;
				}
			default:
				return '';
		}
	}

	/**
	 * Get the recipe author display option.
	 *
	 * @since    1.5.0
	 * @param    boolean $keep_default Wether to replace the default value with the actual one.
	 */
	public function author_display( $keep_default = false ) {
		$author_display = $this->meta( 'wprm_author_display', 'default' );

		if ( ! $keep_default && 'default' === $author_display ) {
			$author_display = WPRM_Settings::get( 'recipe_author_display_default' );
		}

		return $author_display;
	}

	/**
	 * Get the recipe author to use in the metadata.
	 *
	 * @since    1.5.0
	 */
	public function author_meta() {
		switch ( $this->author_display() ) {
			case 'custom':
				return $this->custom_author_name();
			default:
				return $this->post_author_name();
		}
	}

	/**
	 * Get the recipe custom author name.
	 *
	 * @since    1.5.0
	 */
	public function custom_author_name() {
		return $this->meta( 'wprm_author_name', '' );
	}

	/**
	 * Get the recipe custom author link.
	 *
	 * @since    1.20.0
	 */
	public function custom_author_link() {
		return $this->meta( 'wprm_author_link', '' );
	}

	/**
	 * Get the recipe post author name.
	 *
	 * @since    1.5.0
	 */
	public function post_author_name() {
		$author_id = $this->post->post_author;

		if ( $author_id ) {
			$author = get_userdata( $author_id );
			return $author->data->display_name;
		} else {
			return '';
		}
	}

	/**
	 * Get the recipe calories.
	 *
	 * @since    1.0.0
	 */
	public function calories() {
		$nutrition = $this->nutrition();
		return isset( $nutrition['calories'] ) ? $nutrition['calories'] : false;
	}

	/**
	 * Get the recipe publish date.
	 *
	 * @since    1.0.0
	 */
	public function date() {
		return $this->post->post_date;
	}

	/**
	 * Get the recipe ID.
	 *
	 * @since    1.0.0
	 */
	public function id() {
		return $this->post->ID;
	}

	/**
	 * Get the recipe image HTML.
	 *
	 * @since    1.0.0
	 * @param		 mixed $size Thumbnail name or size array of the image we want.
	 */
	public function image( $size = 'thumbnail' ) {
		return wp_get_attachment_image( $this->image_id(), $size );
	}

	/**
	 * Get the recipe image data.
	 *
	 * @since    1.2.0
	 * @param		 mixed $size Thumbnail name or size array of the image we want.
	 */
	public function image_data( $size = 'thumbnail' ) {
		return wp_get_attachment_image_src( $this->image_id(), $size );
	}

	/**
	 * Get the recipe image ID.
	 *
	 * @since    1.0.0
	 */
	public function image_id() {
		$image_id = get_post_thumbnail_id( $this->id() );
		if ( ! $image_id ) {
			$image_id = 0;

			if ( WPRM_Settings::get( 'recipe_image_use_featured' ) ) {
				$parent_image_id = get_post_thumbnail_id( $this->parent_post_id() );

				if ( $parent_image_id ) {
					$image_id = $parent_image_id;
				}
			}
		}
		return $image_id;
	}

	/**
	 * Get the recipe image URL.
	 *
	 * @since    1.0.0
	 * @param		 mixed $size Thumbnail name or size array of the image we want.
	 */
	public function image_url( $size = 'thumbnail' ) {
		$thumb = wp_get_attachment_image_src( $this->image_id(), $size );
		$image_url = $thumb && isset( $thumb[0] ) ? $thumb[0] : '';

		return $image_url;
	}

	/**
	 * Get the recipe name.
	 *
	 * @since    1.0.0
	 */
	public function name() {
		return $this->post->post_title;
	}

	/**
	 * Get the recipe nutrition data.
	 *
	 * @since    1.0.0
	 */
	public function nutrition() {
		return self::unserialize( $this->meta( 'wprm_nutrition', array() ) );
	}

	/**
	 * Does the recipe have a rating?
	 *
	 * @since    1.6.0
	 */
	public function has_rating() {
		$rating = $this->rating();
		return $rating['count'] > 0;
	}

	/**
	 * Get the recipe rating.
	 *
	 * @since    1.1.0
	 */
	public function rating() {
		$rating = self::unserialize( $this->meta( 'wprm_rating', array() ) );

		// Recalculate if rating has not been set yet.
		if ( empty( $rating ) ) {
			$rating = WPRM_Rating::update_recipe_rating( $this->id() );
		}

		// Attach current user rating.
		if ( WPRM_Addons::is_active( 'premium' ) ) {
			$rating['user'] = WPRMP_User_Rating::get_user_rating_for( $this->id() );
		}

		return $rating;
	}

	/**
	 * Get the recipe rating as formatted stars.
	 *
	 * @since    1.6.0
	 * @param    boolean $show_details Wether to display the rating details.
	 */
	public function rating_stars( $show_details = false ) {
		return WPRM_Template_Helper::rating_stars( $this->rating(), $show_details );
	}

	/**
	 * Get the recipe summary.
	 *
	 * @since    1.0.0
	 */
	public function summary() {
		return $this->post->post_content;
	}

	/**
	 * Get the recipe servings.
	 *
	 * @since    1.0.0
	 */
	public function servings() {
		return $this->meta( 'wprm_servings', 0 );
	}

	/**
	 * Get the recipe servings unit.
	 *
	 * @since    1.0.0
	 */
	public function servings_unit() {
		return $this->meta( 'wprm_servings_unit', '' );
	}

	/**
	 * Get the recipe prep time.
	 *
	 * @since    1.0.0
	 */
	public function prep_time() {
		return $this->meta( 'wprm_prep_time', 0 );
	}

	/**
	 * Get the formatted recipe prep time.
	 *
	 * @since    1.6.0
	 * @param    boolean $shorthand Wether to use shorthand for the unit text.
	 */
	public function prep_time_formatted( $shorthand = false ) {
		return WPRM_Template_Helper::time( 'prep_time', $this->prep_time(), $shorthand );
	}

	/**
	 * Get the recipe cook time.
	 *
	 * @since    1.0.0
	 */
	public function cook_time() {
		return $this->meta( 'wprm_cook_time', 0 );
	}

	/**
	 * Get the formatted recipe cook time.
	 *
	 * @since    1.6.0
	 * @param    boolean $shorthand Wether to use shorthand for the unit text.
	 */
	public function cook_time_formatted( $shorthand = false ) {
		return WPRM_Template_Helper::time( 'cook_time', $this->cook_time(), $shorthand );
	}

	/**
	 * Get the recipe total time.
	 *
	 * @since    1.0.0
	 */
	public function total_time() {
		return $this->meta( 'wprm_total_time', 0 );
	}

	/**
	 * Get the formatted recipe total time.
	 *
	 * @since    1.6.0
	 * @param    boolean $shorthand Wether to use shorthand for the unit text.
	 */
	public function total_time_formatted( $shorthand = false ) {
		return WPRM_Template_Helper::time( 'total_time', $this->total_time(), $shorthand );
	}

	/**
	 * Get the recipe tags for a certain tag type.
	 *
	 * @since    1.0.0
	 * @param		 mixed $taxonomy Taxonomy to get the tags for.
	 */
	public function tags( $taxonomy ) {
		$taxonomy = 'wprm_' . $taxonomy;
		$terms = get_the_terms( $this->id(), $taxonomy );

		return is_array( $terms ) ? $terms : array();
	}

	/**
	 * Get the template for this recipe.
	 *
	 * @since    1.0.0
	 * @param		 mixed $type Type of template to get, defaults to single.
	 */
	public function template( $type = 'single' ) {
		return WPRM_Template_Manager::get_template( $this, $type );
	}

	/**
	 * Get the recipe ingredients.
	 *
	 * @since    1.0.0
	 */
	public function ingredients() {
		return self::unserialize( $this->meta( 'wprm_ingredients', array() ) );
	}

	/**
	 * Get the recipe ingredient links type.
	 *
	 * @since    1.14.1
	 */
	public function ingredient_links_type() {
		return $this->meta( 'wprm_ingredient_links_type', 'global' );
	}

	/**
	 * Get the recipe ingredients without nested groups.
	 *
	 * @since    1.0.0
	 */
	public function ingredients_without_groups() {
		$ingredients = $this->ingredients();
		$ingredients_without_groups = array();

		foreach ( $ingredients as $ingredient_group ) {
			$ingredients_without_groups = array_merge( $ingredients_without_groups, $ingredient_group['ingredients'] );
		}

		return $ingredients_without_groups;
	}

	/**
	 * Get the recipe instructions.
	 *
	 * @since    1.0.0
	 */
	public function instructions() {
		return self::unserialize( $this->meta( 'wprm_instructions', array() ) );
	}

	/**
	 * Get the recipe instructions without nested groups.
	 *
	 * @since    1.0.0
	 */
	public function instructions_without_groups() {
		$instructions = $this->instructions();
		$instructions_without_groups = array();

		foreach ( $instructions as $instruction_group ) {
			$instructions_without_groups = array_merge( $instructions_without_groups, $instruction_group['instructions'] );
		}

		return $instructions_without_groups;
	}

	/**
	 * Get the recipe notes.
	 *
	 * @since    1.0.0
	 */
	public function notes() {
		return $this->meta( 'wprm_notes', '' );
	}

	/**
	 * Get the parent post ID.
	 *
	 * @since    1.0.0
	 */
	public function parent_post_id() {
		return $this->meta( 'wprm_parent_post_id', 0 );
	}

	/**
	 * Get the parent post URL.
	 *
	 * @since    1.16.0
	 */
	public function parent_url() {
		$parent_post_id = $this->parent_post_id();
		return $parent_post_id ? get_permalink( $parent_post_id ) : '';
	}
}
