# Mothership Commerce

The `Message\Mothership\Commerce` cogule provides base commerce functionality for Mothership. This forms part of the building blocks for both `ECommerce` and `EPOS`.

## Installation

Install this package using [Composer](http://getcomposer.org/). The package name is `message/cog-mothership-commerce`.

You will need to add Message's private package server to the `repositories` key in `composer.json`:

	{
		"repositories": [
			{
				"type": "composer",
				"url" : "http://packages.message.co.uk"
			}
		],
		"require": {
			"message/cog-mothership-commerce": "1.0.*"
		}
	}

## Todo

* Add `Product` field type for the CMS
	* This will require changes to how the CMS finds fields (currently it only looks within it's own cogule)
* Add `Product` library
* Add `Gateway` interfaces & library
* Add stock & stock movements stuff
* Revisit monetary value storage in `order_item`, `order_summary` etc
* Revisit product options storage in `order_item`
* Revisit product localised storage in `order_item`
* Add comments to all columns in database tables
* Figure out how to easily get the *first* shipping on an order?
	* Might be easier to just not allow multiple shipping for now