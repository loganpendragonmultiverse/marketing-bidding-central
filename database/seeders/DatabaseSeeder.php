<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MarketplaceSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categories = [
            ['AI & Automation', 'ai-automation', 'Applied AI, agents, workflows, and practical automation.'],
            ['Developer Tools', 'developer-tools', 'Tools that help people build, test, ship, and maintain software.'],
            ['SaaS', 'saas', 'Focused software services for teams and businesses.'],
            ['Games', 'games', 'Independent games, interactive worlds, and playful experiments.'],
            ['Creative Tools', 'creative-tools', 'Design, media, writing, music, and creator software.'],
            ['Commerce', 'commerce', 'Products and infrastructure for buying, selling, and operations.'],
            ['Education', 'education', 'Learning products, training, and knowledge tools.'],
            ['Open Source', 'open-source', 'Publicly available software and community projects.'],
            ['Other', 'other', 'Useful projects that do not fit the primary categories yet.'],
        ];
        foreach ($categories as $index => [$name, $slug, $description]) {
            Category::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description, 'active' => true, 'sort_order' => ($index + 1) * 10]);
        }
        MarketplaceSetting::query()->updateOrCreate(['key' => 'minimum_bid_cents'], ['value' => '500', 'type' => 'integer', 'is_public' => true]);
        MarketplaceSetting::query()->updateOrCreate(['key' => 'market_open'], ['value' => '0', 'type' => 'boolean', 'is_public' => true]);
    }
}
