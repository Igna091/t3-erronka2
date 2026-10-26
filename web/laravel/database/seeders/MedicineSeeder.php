<?php

namespace Database\Seeders;

use App\Models\Medicine;
use Illuminate\Database\Seeder;

/**
 * The shop's catalogue from the design. Runs on every deploy (the migrate service seeds), so it
 * updates rows by slug instead of adding duplicates.
 *
 * Prices are the design's example prices. Doses and most warnings are placeholders: they must
 * be copied from each medicine's package leaflet on CIMA, never written from memory.
 */
class MedicineSeeder extends Seeder
{
    private const DOSAGE = '[Dose for adults and for children, and the most you can take in 24 hours, copied from the package leaflet]';

    private const WARNINGS = '[Warnings from the package leaflet]';

    public function run(): void
    {
        $medicines = [
            ['paracetamol-500-mg', 'Paracetamol 500 mg', '20 tablets', 'Headache, toothache, muscle pain and fever', 'Short-term relief of mild to moderate pain, such as headache, toothache or muscle pain, and of fever.', 'pain', 'box', 195,
                ['Don’t take it with other medicines that contain paracetamol, such as some cold and flu remedies.', '[Other warnings from the package leaflet]']],
            ['ibuprofen-200-mg', 'Ibuprofen 200 mg', '20 coated tablets', 'Pain, fever and inflammation', 'Short-term relief of mild to moderate pain, fever and inflammation.', 'pain', 'box', 320, [self::WARNINGS]],
            ['acetylsalicylic-acid-500-mg', 'Acetylsalicylic acid 500 mg', '20 tablets', 'Pain and fever in adults', 'Short-term relief of mild to moderate pain and of fever in adults.', 'pain', 'box', 290, [self::WARNINGS]],
            ['omeprazole-20-mg', 'Omeprazole 20 mg', '14 gastro-resistant capsules', 'Heartburn and acid reflux in adults', 'Short-term relief of heartburn and acid reflux in adults.', 'stomach', 'box', 495, [self::WARNINGS]],
            ['almagate-500-mg', 'Almagate 500 mg', '30 chewable tablets', 'Heartburn and indigestion', 'Relief of heartburn and indigestion.', 'stomach', 'box', 640, [self::WARNINGS]],
            ['loperamide-2-mg', 'Loperamide 2 mg', '10 capsules', 'Short bouts of diarrhoea', 'Short-term relief of sudden diarrhoea.', 'stomach', 'box', 530, [self::WARNINGS]],
            ['ambroxol-15-mg-5-ml', 'Ambroxol 15 mg/5 ml', 'Oral solution, 200 ml', 'Cough with mucus', 'Helps loosen the mucus in a cough with phlegm.', 'cough', 'bottle', 590, [self::WARNINGS]],
            ['chlorhexidine-10-mg-ml', 'Chlorhexidine 10 mg/ml', 'Skin solution, 25 ml', 'Cleaning small cuts and grazes', 'Cleans and disinfects small cuts and grazes.', 'skin', 'bottle', 360, [self::WARNINGS]],
        ];

        $now = now();
        $rows = array_map(fn (array $m) => [
            'slug' => $m[0],
            'name' => $m[1],
            'pack' => $m[2],
            'summary' => $m[3],
            'description' => $m[4],
            'dosage' => self::DOSAGE,
            'warnings' => json_encode($m[8]),
            'category' => $m[5],
            'form' => $m[6],
            'price_cents' => $m[7],
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ], $medicines);

        Medicine::upsert($rows, uniqueBy: ['slug'], update: [
            'name', 'pack', 'summary', 'description', 'dosage', 'warnings', 'category', 'form', 'price_cents', 'active', 'updated_at',
        ]);
    }
}
