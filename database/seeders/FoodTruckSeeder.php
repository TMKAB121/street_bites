<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\StoreTruckImage;
use App\Models\FoodTruck;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Seeds 3 vendor users, the full cuisine tag taxonomy, and ~10 published food
 * trucks across those tags. Fixture images from database/seeders/fixtures/images/
 * are processed through StoreTruckImage (resize → 250×250 WebP) so the seeded
 * data exercises the full image pipeline. Re-running is idempotent for tags
 * (firstOrCreate by slug); it will create duplicate trucks/users unless you
 * run migrate:fresh --seed instead.
 *
 * Usage: lando artisan migrate:fresh --seed
 */
class FoodTruckSeeder extends Seeder
{
    public function run(): void
    {
        // Taxonomy — finds-or-creates by slug so re-seeding doesn't duplicate.
        $tagNames = ['Asian', 'BBQ', 'Burgers', 'Dessert', 'Mediterranean', 'Mexican', 'Pizza', 'Seafood', 'Tacos', 'Vegan'];

        /** @var array<string, Tag> $tags */
        $tags = [];
        foreach ($tagNames as $name) {
            $tags[$name] = Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        // Fixture images: shuffle so assignment varies between seeds.
        $imagePaths = collect(glob(database_path('seeders/fixtures/images/*')))
            ->filter(fn (string $p): bool => is_file($p))
            ->shuffle()
            ->values();

        $imageCount = $imagePaths->count();

        // Vendor accounts.
        $vendor1 = User::factory()->create(['name' => 'Marco Rossi',  'email' => 'marco@streetbites.test']);
        $vendor2 = User::factory()->create(['name' => 'Priya Nair',   'email' => 'priya@streetbites.test']);
        $vendor3 = User::factory()->create(['name' => 'Jamie Okafor', 'email' => 'jamie@streetbites.test']);

        // Truck definitions: [vendor, name, description, tag keys, menu items].
        $truckData = [
            [
                'vendor' => $vendor1,
                'name' => "Smokin' Wheels BBQ",
                'desc' => 'Slow-smoked Texas-style brisket, ribs, and pulled pork served with house pickles.',
                'tags' => ['BBQ'],
                'menu' => [
                    ['Brisket Plate', 1800],
                    ['Pulled Pork Sandwich', 1200],
                    ['Smoked Ribs (half rack)', 2200],
                    ['Coleslaw', 400],
                    ['Sweet Tea', 350],
                ],
            ],
            [
                'vendor' => $vendor1,
                'name' => 'Taco Libre',
                'desc' => 'Handcrafted street tacos with house-made salsas and fresh corn tortillas.',
                'tags' => ['Mexican', 'Tacos'],
                'menu' => [
                    ['Al Pastor Taco', 450],
                    ['Carnitas Taco', 450],
                    ['Veggie Taco', 400],
                    ['Chips & Guac', 600],
                    ['Agua Fresca', 350],
                ],
            ],
            [
                'vendor' => $vendor1,
                'name' => 'Verde Kitchen',
                'desc' => 'Plant-based bowls, wraps, and smoothies — bold flavour, zero compromise.',
                'tags' => ['Vegan'],
                'menu' => [
                    ['Buddha Bowl', 1300],
                    ['Falafel Wrap', 1100],
                    ['Green Smoothie', 700],
                    ['Roasted Veg Salad', 950],
                ],
            ],
            [
                'vendor' => $vendor2,
                'name' => 'Burger Bloc',
                'desc' => 'Crispy smash burgers stacked with premium toppings on toasted brioche buns.',
                'tags' => ['Burgers'],
                'menu' => [
                    ['Classic Smash', 1100],
                    ['Double Smash', 1500],
                    ['Cheese Fries', 600],
                    ['Milkshake', 700],
                    ['Onion Rings', 500],
                ],
            ],
            [
                'vendor' => $vendor2,
                'name' => 'Nacho Average',
                'desc' => 'Loaded nachos, quesadillas, and churros — the ultimate Mexican street snacks.',
                'tags' => ['Mexican', 'Dessert'],
                'menu' => [
                    ['Loaded Nachos', 1100],
                    ['Chicken Quesadilla', 1000],
                    ['Churros (4)', 600],
                    ['Horchata', 400],
                ],
            ],
            [
                'vendor' => $vendor2,
                'name' => 'Curry Cart',
                'desc' => 'South Asian street food: fragrant curries, golden samosas, and masala chai.',
                'tags' => ['Asian'],
                'menu' => [
                    ['Butter Chicken Curry', 1300],
                    ['Daal & Rice', 1000],
                    ['Samosa (2)', 500],
                    ['Mango Lassi', 500],
                    ['Masala Chai', 350],
                ],
            ],
            [
                'vendor' => $vendor3,
                'name' => 'Waffle Wagon',
                'desc' => 'Belgian waffles with indulgent sweet and savoury toppings made to order.',
                'tags' => ['Dessert'],
                'menu' => [
                    ['Classic Waffle', 800],
                    ['Nutella & Strawberry Waffle', 1000],
                    ['Savoury Bacon & Egg Waffle', 1100],
                    ['Iced Coffee', 500],
                ],
            ],
            [
                'vendor' => $vendor3,
                'name' => 'Pier 7 Seafood',
                'desc' => 'Fresh fish tacos, clam chowder cups, and grilled shrimp skewers from the coast.',
                'tags' => ['Seafood', 'Tacos'],
                'menu' => [
                    ['Fish Taco', 550],
                    ['Clam Chowder Cup', 800],
                    ['Shrimp Skewer', 1200],
                    ['Calamari', 950],
                    ['Lemonade', 350],
                ],
            ],
            [
                'vendor' => $vendor3,
                'name' => 'Pizza Peddler',
                'desc' => 'Wood-fired Neapolitan-style pizza slices — crispy crust, San Marzano tomatoes.',
                'tags' => ['Pizza'],
                'menu' => [
                    ['Margherita Slice', 500],
                    ['Pepperoni Slice', 600],
                    ['Veggie Slice', 550],
                    ['Whole Margherita', 1800],
                    ['Sparkling Water', 300],
                ],
            ],
            [
                'vendor' => $vendor3,
                'name' => 'Seoul Bowl',
                'desc' => 'Korean BBQ bowls, bibimbap, and kimchi fries — big umami energy.',
                'tags' => ['Asian', 'BBQ'],
                'menu' => [
                    ['Bulgogi Bowl', 1300],
                    ['Bibimbap', 1200],
                    ['Kimchi Fries', 800],
                    ['Korean Fried Chicken', 1100],
                    ['Barley Tea', 300],
                ],
            ],
        ];

        $storeTruckImage = new StoreTruckImage;

        foreach ($truckData as $i => $data) {
            $truck = FoodTruck::query()->create([
                'user_id' => $data['vendor']->id,
                'name' => $data['name'],
                'description' => $data['desc'],
                'is_published' => true,
            ]);

            $tagIds = array_map(fn (string $t): int => $tags[$t]->id, $data['tags']);
            $truck->tags()->sync($tagIds);

            $menuRows = [];
            foreach ($data['menu'] as $order => [$itemName, $priceCents]) {
                $menuRows[] = [
                    'name' => $itemName,
                    'price_cents' => $priceCents,
                    'sort_order' => $order,
                    'is_available' => true,
                ];
            }
            $truck->menuItems()->createMany($menuRows);

            if ($imageCount > 0) {
                // Cycle through the shuffled pool so every fixture image is used
                // at least once across the 10 trucks. Even-indexed trucks also get
                // a second image offset by half the pool, giving some variety.
                $this->attachImage($storeTruckImage, $truck, $imagePaths[$i % $imageCount]);

                if ($i % 2 === 0 && $imageCount > 1) {
                    $this->attachImage($storeTruckImage, $truck, $imagePaths[($i + intdiv($imageCount, 2)) % $imageCount]);
                }
            }
        }
    }

    /**
     * Wrap a fixture file in an UploadedFile (test mode skips the is_uploaded_file
     * check) and run it through the same StoreTruckImage pipeline the UI uses:
     * centre-crop → 250×250 → WebP → public disk. Unsupported formats (e.g. AVIF
     * when GD lacks libavif) are skipped with a warning rather than aborting.
     */
    private function attachImage(StoreTruckImage $action, FoodTruck $truck, string $path): void
    {
        $mimeType = mime_content_type($path) ?: 'image/jpeg';

        $file = new UploadedFile(
            path: $path,
            originalName: basename($path),
            mimeType: $mimeType,
            error: null,
            test: true,
        );

        try {
            $action($truck, $file);
        } catch (\Throwable $e) {
            $this->command->warn('  Skipped '.basename($path).': '.$e->getMessage());
        }
    }
}
