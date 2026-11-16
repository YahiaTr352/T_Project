<?php

namespace Database\Seeders;

use App\Models\WalkingTrek;
use App\Models\WalkingTrekWaypoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WalkingSaudiTrekSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Deactivate any existing active community treks to avoid conflicts
            WalkingTrek::where('target_type', 'family_community')
                ->where('status', 'active')
                ->update(['status' => 'completed']);

            $trek = WalkingTrek::create([
                'title'             => 'طواف المملكة - رحلة العائلة الكبرى',
                'description'       => 'رحلة مشي تفاعلية تجمع خطوات ومسافات جميع أفراد العائلة معاً لنطوف مدن ومنازل المملكة في مسار واحد متصل.',
                'total_distance_km' => 2760.00,
                'start_date'        => null,
                'end_date'          => null,
                'status'            => 'active',
                'target_type'       => 'family_community',
                'is_loop'           => true,
            ]);

            $waypoints = [
                [
                    'sort_order'            => 1,
                    'city_name'             => 'الرياض',
                    'location_title'        => 'منزل العائلة بالرياض',
                    'location_description'  => 'نقطة الانطلاق الرسمية للرحلة',
                    'latitude'              => 24.7136000,
                    'longitude'             => 46.6753000,
                    'distance_from_prev_km' => 0.00,
                    'cumulative_km'         => 0.00,
                    'icon_type'             => 'home',
                ],
                [
                    'sort_order'            => 2,
                    'city_name'             => 'الطائف',
                    'location_title'        => 'الطائف - عروس المصائف',
                    'location_description'  => 'عبور جبال السروات عبر طريق الرياض - الطائف',
                    'latitude'              => 21.2854000,
                    'longitude'             => 40.4244000,
                    'distance_from_prev_km' => 780.00,
                    'cumulative_km'         => 780.00,
                    'icon_type'             => 'landmark',
                ],
                [
                    'sort_order'            => 3,
                    'city_name'             => 'مكة المكرمة',
                    'location_title'        => 'منزل العائلة بمكة المكرمة',
                    'location_description'  => 'مهوى الأفئدة وقبلة المسلمين',
                    'latitude'              => 21.3891000,
                    'longitude'             => 39.8579000,
                    'distance_from_prev_km' => 85.00,
                    'cumulative_km'         => 865.00,
                    'icon_type'             => 'mosque',
                ],
                [
                    'sort_order'            => 4,
                    'city_name'             => 'جدة',
                    'location_title'        => 'منزل العائلة بجدة',
                    'location_description'  => 'عروس البحر الأحمر عبر طريق الحرمين',
                    'latitude'              => 21.5433000,
                    'longitude'             => 39.1728000,
                    'distance_from_prev_km' => 75.00,
                    'cumulative_km'         => 940.00,
                    'icon_type'             => 'home',
                ],
                [
                    'sort_order'            => 5,
                    'city_name'             => 'المدينة المنورة',
                    'location_title'        => 'طيبة الطيبة - المدينة المنورة',
                    'location_description'  => 'مدينة رسول الله ﷺ عبر طريق الهجرة السريع',
                    'latitude'              => 24.5247000,
                    'longitude'             => 39.5692000,
                    'distance_from_prev_km' => 420.00,
                    'cumulative_km'         => 1360.00,
                    'icon_type'             => 'mosque',
                ],
                [
                    'sort_order'            => 6,
                    'city_name'             => 'العلا',
                    'location_title'        => 'العلا التاريخية',
                    'location_description'  => 'عاصمة الآثار والحضارات عبر طريق خيبر',
                    'latitude'              => 26.6167000,
                    'longitude'             => 37.9167000,
                    'distance_from_prev_km' => 330.00,
                    'cumulative_km'         => 1690.00,
                    'icon_type'             => 'landmark',
                ],
                [
                    'sort_order'            => 7,
                    'city_name'             => 'حائل',
                    'location_title'        => 'حائل - عروس الشمال',
                    'location_description'  => 'ديار حاتم الطائي وجبال أجا وسلمى',
                    'latitude'              => 27.5219000,
                    'longitude'             => 41.6961000,
                    'distance_from_prev_km' => 440.00,
                    'cumulative_km'         => 2130.00,
                    'icon_type'             => 'city',
                ],
                [
                    'sort_order'            => 8,
                    'city_name'             => 'القصيم',
                    'location_title'        => 'منزل العائلة بالقصيم',
                    'location_description'  => 'واحة النخيل وبريدة عبر طريق حائل السريع',
                    'latitude'              => 26.3260000,
                    'longitude'             => 43.9750000,
                    'distance_from_prev_km' => 280.00,
                    'cumulative_km'         => 2410.00,
                    'icon_type'             => 'home',
                ],
                [
                    'sort_order'            => 9,
                    'city_name'             => 'الرياض',
                    'location_title'        => 'العودة للرياض - نقطة الختام',
                    'location_description'  => 'إتمام الطواف الكامل بحول الله وتوفيقه',
                    'latitude'              => 24.7136000,
                    'longitude'             => 46.6753000,
                    'distance_from_prev_km' => 350.00,
                    'cumulative_km'         => 2760.00,
                    'icon_type'             => 'trophy',
                ],
            ];

            foreach ($waypoints as $data) {
                $data['walking_trek_id'] = $trek->id;
                WalkingTrekWaypoint::create($data);
            }
        });
    }
}
