<?php

namespace App\Services\WorkoutMemory;

class ExerciseCatalogSeederData
{
    /**
     * @return list<array<string, mixed>>
     */
    public function exercises(): array
    {
        $curated = $this->curated();

        return [...$curated, ...$this->importedCatalog($curated)];
    }

    /**
     * Hand-curated catalog only, without the bulk import. The import command
     * dedupes the dataset against this list (never against exercises(), which
     * would dedupe a regenerated import against its own previous output).
     *
     * @return list<array<string, mixed>>
     */
    public function curated(): array
    {
        return [
            ...$this->ringsAndCalisthenics(),
            ...$this->bodyweightStrength(),
            ...$this->handstandAndSkill(),
            ...$this->compressionMobilityCore(),
            ...$this->strength(),
            ...$this->conditioning(),
        ];
    }

    /**
     * Bulk gym vocabulary imported from free-exercise-db (public domain,
     * https://github.com/yuhonas/free-exercise-db), pre-transformed to the
     * seeder shape by scripts/import-free-exercise-db.php. Curated entries
     * always win: an imported exercise whose name collides with a curated
     * name or alias (ignoring spacing and a trailing plural "s") is skipped.
     *
     * @param  list<array<string, mixed>>  $curated
     * @return list<array<string, mixed>>
     */
    private function importedCatalog(array $curated): array
    {
        $path = base_path('database/seeders/data/imported-exercise-catalog.json');

        if (! is_file($path)) {
            return [];
        }

        $taken = collect($curated)
            ->flatMap(fn (array $exercise): array => [$exercise['name'], ...$exercise['aliases']])
            ->map(fn (string $name): string => self::dedupeKey($name))
            ->flip();

        return collect(json_decode((string) file_get_contents($path), true) ?: [])
            ->reject(fn (array $exercise): bool => $taken->has(self::dedupeKey((string) $exercise['name'])))
            ->values()
            ->all();
    }

    public static function dedupeKey(string $name): string
    {
        return rtrim(str_replace(' ', '', ExerciseResolver::normalize($name)), 's');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ringsAndCalisthenics(): array
    {
        return [
            $this->exercise('Ring Muscle-Up', 'rings', 'reps', ['rings'], ['lats', 'chest', 'triceps'], ['ring MU', 'rings MU']),
            $this->exercise('Weighted Ring Muscle-Up', 'rings', 'load_reps', ['rings', 'weight belt'], ['lats', 'chest', 'triceps'], ['weighted rings MU', 'weighted ring MU', 'ring muscle-up +kg'], 'variant', true, 'Ring Muscle-Up'),
            $this->exercise('Strict Ring Muscle-Up', 'rings', 'reps', ['rings'], ['lats', 'chest', 'triceps'], ['strict ring MU'], 'variant', false, 'Ring Muscle-Up'),
            $this->exercise('Ring Muscle-Up Negative', 'rings', 'reps', ['rings'], ['lats', 'chest'], ['slow negative ring muscle-up', 'ring MU negative'], 'variant', false, 'Ring Muscle-Up'),
            $this->exercise('Ring Transition Drill', 'rings', 'reps', ['rings'], ['chest', 'triceps'], ['low ring transition']),
            $this->exercise('False Grip Hang', 'rings', 'hold', ['rings'], ['forearms', 'lats'], ['FG hang']),
            $this->exercise('False Grip Ring Row', 'rings', 'reps', ['rings'], ['lats', 'forearms']),
            $this->exercise('Ring Pull-Up', 'rings', 'reps', ['rings'], ['lats', 'biceps'], ['rings pull-up']),
            $this->exercise('Weighted Ring Pull-Up', 'rings', 'load_reps', ['rings', 'weight belt'], ['lats', 'biceps'], ['weighted rings pull-up'], 'variant', true, 'Ring Pull-Up'),
            $this->exercise('Ring Row', 'rings', 'reps', ['rings'], ['lats', 'rear delts']),
            $this->exercise('Feet-Elevated Ring Row', 'rings', 'reps', ['rings', 'box'], ['lats', 'rear delts'], [], 'variant', false, 'Ring Row'),
            $this->exercise('Ring Dip', 'rings', 'reps', ['rings'], ['chest', 'triceps'], ['ring dips']),
            $this->exercise('Weighted Ring Dip', 'rings', 'load_reps', ['rings', 'weight belt'], ['chest', 'triceps'], ['weighted ring dips'], 'variant', true, 'Ring Dip'),
            $this->exercise('Ring Dip Negative', 'rings', 'reps', ['rings'], ['chest', 'triceps'], [], 'variant', false, 'Ring Dip'),
            $this->exercise('Ring Support Hold', 'rings', 'hold', ['rings'], ['shoulders', 'triceps']),
            $this->exercise('Ring Turned-Out Support Hold', 'rings', 'hold', ['rings'], ['shoulders', 'triceps'], ['RTO support', 'turned out support']),
            $this->exercise('Ring Push-Up', 'rings', 'reps', ['rings'], ['chest', 'triceps']),
            $this->exercise('Ring Archer Push-Up', 'rings', 'reps', ['rings'], ['chest', 'triceps']),
            $this->exercise('Ring Pike Push-Up', 'rings', 'reps', ['rings'], ['shoulders', 'triceps']),
            $this->exercise('Ring Fly', 'rings', 'reps', ['rings'], ['chest']),
            $this->exercise('Ring Face Pull', 'rings', 'reps', ['rings'], ['rear delts', 'upper back']),
            $this->exercise('Skin-the-Cat', 'rings', 'reps', ['rings'], ['shoulders', 'core']),
            $this->exercise('German Hang', 'rings', 'hold', ['rings'], ['shoulders', 'chest']),
            $this->exercise('Front Lever Tuck Hold', 'rings', 'hold', ['rings', 'pull-up bar'], ['lats', 'core']),
            $this->exercise('Front Lever Advanced Tuck Hold', 'rings', 'hold', ['rings', 'pull-up bar'], ['lats', 'core'], [], 'variant', false, 'Front Lever Tuck Hold'),
            $this->exercise('Front Lever Raise', 'rings', 'reps', ['rings', 'pull-up bar'], ['lats', 'core']),
            $this->exercise('Back Lever Tuck Hold', 'rings', 'hold', ['rings'], ['shoulders', 'core']),
            $this->exercise('L-Sit On Rings', 'rings', 'hold', ['rings'], ['core', 'hip flexors'], ['ring l-sit']),
            $this->exercise('Tuck L-Sit On Rings', 'rings', 'hold', ['rings'], ['core', 'hip flexors'], [], 'variant', false, 'L-Sit On Rings'),
            $this->exercise('Rings Skill Drill', 'rings', 'mixed', ['rings'], ['skill'], ['rings skill work'], 'bucket'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bodyweightStrength(): array
    {
        return [
            $this->exercise('Pull-Up', 'calisthenics', 'reps', ['pull-up bar'], ['lats', 'biceps'], ['pullups']),
            $this->exercise('Weighted Pull-Up', 'calisthenics', 'load_reps', ['pull-up bar', 'weight belt'], ['lats', 'biceps'], ['weighted pullups'], 'variant', true, 'Pull-Up'),
            $this->exercise('Chin-Up', 'calisthenics', 'reps', ['pull-up bar'], ['lats', 'biceps']),
            $this->exercise('Weighted Chin-Up', 'calisthenics', 'load_reps', ['pull-up bar', 'weight belt'], ['lats', 'biceps'], [], 'variant', true, 'Chin-Up'),
            $this->exercise('Chest-to-Bar Pull-Up', 'calisthenics', 'reps', ['pull-up bar'], ['lats', 'upper back'], ['C2B pull-up'], 'variant', false, 'Pull-Up'),
            $this->exercise('Archer Pull-Up', 'calisthenics', 'reps', ['pull-up bar'], ['lats', 'biceps'], [], 'variant', false, 'Pull-Up'),
            $this->exercise('Scapular Pull-Up', 'calisthenics', 'reps', ['pull-up bar'], ['scapulae', 'lats']),
            $this->exercise('Inverted Row', 'calisthenics', 'reps', ['bar', 'rings'], ['lats', 'rear delts']),
            $this->exercise('Push-Up', 'calisthenics', 'reps', ['bodyweight'], ['chest', 'triceps'], ['pushups', 'push ups']),
            $this->exercise('Weighted Push-Up', 'calisthenics', 'load_reps', ['bodyweight', 'plate'], ['chest', 'triceps'], [], 'variant', true, 'Push-Up'),
            $this->exercise('Pike Push-Up', 'calisthenics', 'reps', ['bodyweight'], ['shoulders', 'triceps']),
            $this->exercise('Elevated Pike Push-Up', 'calisthenics', 'reps', ['box'], ['shoulders', 'triceps'], [], 'variant', false, 'Pike Push-Up'),
            $this->exercise('Handstand Push-Up', 'calisthenics', 'reps', ['wall', 'bodyweight'], ['shoulders', 'triceps'], ['HSPU']),
            $this->exercise('Wall Handstand Push-Up', 'calisthenics', 'reps', ['wall'], ['shoulders', 'triceps'], ['wall HSPU'], 'variant', false, 'Handstand Push-Up'),
            $this->exercise('Handstand Push-Up Negative', 'calisthenics', 'reps', ['wall'], ['shoulders', 'triceps'], ['HSPU negative'], 'variant', false, 'Handstand Push-Up'),
            $this->exercise('Dip', 'calisthenics', 'reps', ['parallel bars'], ['chest', 'triceps']),
            $this->exercise('Weighted Dip', 'calisthenics', 'load_reps', ['parallel bars', 'weight belt'], ['chest', 'triceps'], [], 'variant', true, 'Dip'),
            $this->exercise('Bench Dip', 'calisthenics', 'reps', ['bench'], ['triceps']),
            $this->exercise('Diamond Push-Up', 'calisthenics', 'reps', ['bodyweight'], ['triceps', 'chest']),
            $this->exercise('Hollow Body Hold', 'calisthenics', 'hold', ['bodyweight'], ['core']),
            $this->exercise('Arch Body Hold', 'calisthenics', 'hold', ['bodyweight'], ['lower back', 'glutes']),
            $this->exercise('Plank', 'calisthenics', 'hold', ['bodyweight'], ['core']),
            $this->exercise('Side Plank', 'calisthenics', 'hold', ['bodyweight'], ['obliques']),
            $this->exercise('Toes-to-Bar', 'calisthenics', 'reps', ['pull-up bar'], ['core', 'lats'], ['TTB']),
            $this->exercise('Hanging Leg Raise', 'calisthenics', 'reps', ['pull-up bar'], ['core', 'hip flexors']),
            $this->exercise('Hanging Knee Raise', 'calisthenics', 'reps', ['pull-up bar'], ['core', 'hip flexors']),
            $this->exercise('Dragon Flag', 'calisthenics', 'reps', ['bench'], ['core']),
            $this->exercise('Nordic Hamstring Curl', 'calisthenics', 'reps', ['bodyweight'], ['hamstrings']),
            $this->exercise('Reverse Nordic Curl', 'calisthenics', 'reps', ['bodyweight'], ['quads']),
            $this->exercise('Bodyweight Squat', 'calisthenics', 'reps', ['bodyweight'], ['quads', 'glutes']),
            $this->exercise('Jump Squat', 'calisthenics', 'reps', ['bodyweight'], ['quads', 'glutes']),
            $this->exercise('Single-Leg Glute Bridge', 'calisthenics', 'reps', ['bodyweight'], ['glutes', 'hamstrings']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function handstandAndSkill(): array
    {
        return [
            $this->exercise('Handstand Practice Block', 'handstand', 'mixed', ['wall', 'bodyweight'], ['skill'], ['handstand work'], 'bucket'),
            $this->exercise('Handstand Accessory Drill', 'handstand', 'mixed', ['wall', 'bodyweight'], ['skill'], ['handstand accessory', 'handstand drills', 'handstand drill block'], 'bucket'),
            $this->exercise('Handstand Hold', 'handstand', 'hold', ['bodyweight'], ['shoulders', 'core'], ['HS hold']),
            $this->exercise('Freestanding Handstand Hold', 'handstand', 'hold', ['bodyweight'], ['shoulders', 'core'], ['free HS hold'], 'variant', false, 'Handstand Hold'),
            $this->exercise('Wall Handstand Hold', 'handstand', 'hold', ['wall'], ['shoulders', 'core'], ['wall HS hold'], 'variant', false, 'Handstand Hold'),
            $this->exercise('Chest-to-Wall Handstand Hold', 'handstand', 'hold', ['wall'], ['shoulders', 'core'], ['CTW handstand'], 'variant', false, 'Wall Handstand Hold'),
            $this->exercise('Back-to-Wall Handstand Hold', 'handstand', 'hold', ['wall'], ['shoulders', 'core'], ['BTW handstand'], 'variant', false, 'Wall Handstand Hold'),
            $this->exercise('Wall Tuck Handstand Hold', 'handstand', 'hold', ['wall'], ['shoulders', 'core'], ['wall tuck HS', 'tuck handstand by wall'], 'variant', false, 'Wall Handstand Hold'),
            $this->exercise('Wall Walk', 'handstand', 'reps', ['wall'], ['shoulders', 'core']),
            $this->exercise('Handstand Kick-Up', 'handstand', 'attempts', ['bodyweight'], ['skill'], ['HS kick-up']),
            $this->exercise('Handstand Shoulder Tap', 'handstand', 'reps', ['wall'], ['shoulders', 'core']),
            $this->exercise('Handstand Weight Shift', 'handstand', 'reps', ['wall'], ['shoulders', 'core']),
            $this->exercise('Handstand Line Drill', 'handstand', 'mixed', ['wall'], ['skill']),
            $this->exercise('Handstand Shrug', 'handstand', 'reps', ['wall'], ['shoulders', 'serratus']),
            $this->exercise('Frog Stand', 'handstand', 'hold', ['bodyweight'], ['shoulders', 'core']),
            $this->exercise('Crow Pose', 'handstand', 'hold', ['bodyweight'], ['shoulders', 'core']),
            $this->exercise('Tuck Planche Hold', 'calisthenics', 'hold', ['parallettes', 'bodyweight'], ['shoulders', 'core']),
            $this->exercise('Planche Lean', 'calisthenics', 'hold', ['bodyweight'], ['shoulders', 'core']),
            $this->exercise('Wrist Prep', 'mobility', 'mixed', ['bodyweight'], ['wrists'], ['wrist warmup'], 'bucket'),
            $this->exercise('Wrist Extension Stretch', 'mobility', 'hold', ['bodyweight'], ['wrists']),
            $this->exercise('Wrist Flexion Stretch', 'mobility', 'hold', ['bodyweight'], ['wrists']),
            $this->exercise('Scapular Wall Slide', 'mobility', 'reps', ['wall'], ['shoulders', 'upper back']),
            $this->exercise('Skill Practice Block', 'other', 'mixed', ['bodyweight'], ['skill'], ['skill block'], 'bucket'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function compressionMobilityCore(): array
    {
        return [
            $this->exercise('Compression Drill', 'compression', 'mixed', ['bodyweight'], ['hip flexors', 'core'], ['compression lifts', 'abs compression'], 'bucket'),
            $this->exercise('Seated Single-Leg Compression Lift', 'compression', 'reps', ['bodyweight'], ['hip flexors', 'core'], ['one leg seated compression lifts']),
            $this->exercise('Seated Double-Leg Compression Lift', 'compression', 'reps', ['bodyweight'], ['hip flexors', 'core'], ['seated both-leg compression lift']),
            $this->exercise('Pancake Single-Leg Compression Lift', 'compression', 'reps', ['bodyweight'], ['hip flexors', 'core'], ['pancake one-leg lift']),
            $this->exercise('Pancake Compression Lift', 'compression', 'reps', ['bodyweight'], ['hip flexors', 'core'], ['pancake both-leg compression lift']),
            $this->exercise('Pike Compression Lift', 'compression', 'reps', ['bodyweight'], ['hip flexors', 'core']),
            $this->exercise('L-Sit', 'calisthenics', 'hold', ['parallettes', 'floor'], ['core', 'hip flexors']),
            $this->exercise('Tuck L-Sit', 'calisthenics', 'hold', ['parallettes', 'floor'], ['core', 'hip flexors'], [], 'variant', false, 'L-Sit'),
            $this->exercise('V-Sit Progression', 'calisthenics', 'mixed', ['floor'], ['core', 'hip flexors']),
            $this->exercise('Pancake Stretch', 'mobility', 'hold', ['floor'], ['hamstrings', 'adductors']),
            $this->exercise('Pike Stretch', 'mobility', 'hold', ['floor'], ['hamstrings']),
            $this->exercise('Couch Stretch', 'mobility', 'hold', ['wall'], ['hip flexors', 'quads']),
            $this->exercise('Jefferson Curl', 'mobility', 'load_reps', ['barbell', 'dumbbell'], ['spine', 'hamstrings'], [], 'canonical', true),
            $this->exercise('Deep Squat Hold', 'mobility', 'hold', ['bodyweight'], ['hips', 'ankles']),
            $this->exercise('Cossack Squat', 'mobility', 'reps', ['bodyweight'], ['adductors', 'quads']),
            $this->exercise('Shoulder Dislocate', 'mobility', 'reps', ['stick', 'band'], ['shoulders']),
            $this->exercise('Shoulder Extension Stretch', 'mobility', 'hold', ['floor'], ['shoulders', 'chest']),
            $this->exercise('Thoracic Bridge', 'mobility', 'reps', ['bodyweight'], ['thoracic spine', 'shoulders']),
            $this->exercise('Mobility Flow', 'mobility', 'mixed', ['bodyweight'], ['mobility'], ['random shoulder prep stuff', 'mobility training', 'mobility work', 'leg and shoulder mobility'], 'bucket'),
            $this->exercise('Core Accessory Drill', 'calisthenics', 'mixed', ['bodyweight'], ['core'], ['core accessories'], 'bucket'),
            $this->exercise('Dead Bug', 'calisthenics', 'reps', ['bodyweight'], ['core']),
            $this->exercise('Bird Dog', 'calisthenics', 'reps', ['bodyweight'], ['core', 'glutes']),
            $this->exercise('Pallof Press', 'strength', 'reps', ['cable', 'band'], ['core']),
            $this->exercise('Ab Wheel Rollout', 'strength', 'reps', ['ab wheel'], ['core']),
            $this->exercise('Cable Crunch', 'strength', 'load_reps', ['cable'], ['core'], [], 'canonical', true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function strength(): array
    {
        return [
            $this->exercise('Back Squat', 'strength', 'load_reps', ['barbell'], ['quads', 'glutes'], [], 'canonical', true),
            $this->exercise('Front Squat', 'strength', 'load_reps', ['barbell'], ['quads', 'core'], [], 'canonical', true),
            $this->exercise('Romanian Deadlift', 'strength', 'load_reps', ['barbell'], ['hamstrings', 'glutes'], ['RDL'], 'canonical', true),
            $this->exercise('Conventional Deadlift', 'strength', 'load_reps', ['barbell'], ['posterior chain'], ['deadlift'], 'canonical', true),
            $this->exercise('Trap Bar Deadlift', 'strength', 'load_reps', ['trap bar'], ['posterior chain'], [], 'canonical', true),
            $this->exercise('Barbell Bench Press', 'strength', 'load_reps', ['barbell'], ['chest', 'triceps'], ['bench press'], 'canonical', true),
            $this->exercise('Incline Barbell Bench Press', 'strength', 'load_reps', ['barbell'], ['upper chest', 'triceps'], [], 'variant', true, 'Barbell Bench Press'),
            $this->exercise('Dumbbell Bench Press', 'strength', 'load_reps', ['dumbbells'], ['chest', 'triceps'], ['DB bench'], 'canonical', true),
            $this->exercise('Incline Dumbbell Press', 'strength', 'load_reps', ['dumbbells'], ['upper chest', 'triceps'], ['incline DB press'], 'variant', true, 'Dumbbell Bench Press'),
            $this->exercise('Dumbbell Row', 'strength', 'load_reps', ['dumbbells'], ['lats', 'upper back'], ['DB row'], 'canonical', true),
            $this->exercise('Chest-Supported Row', 'strength', 'load_reps', ['machine', 'dumbbells'], ['upper back', 'lats'], [], 'canonical', true),
            $this->exercise('Barbell Row', 'strength', 'load_reps', ['barbell'], ['lats', 'upper back'], [], 'canonical', true),
            $this->exercise('Seated Cable Row', 'strength', 'load_reps', ['cable'], ['lats', 'upper back'], [], 'canonical', true),
            $this->exercise('Lat Pulldown', 'strength', 'load_reps', ['cable'], ['lats'], [], 'canonical', true),
            $this->exercise('Standing Overhead Press', 'strength', 'load_reps', ['barbell'], ['shoulders', 'triceps'], ['OHP'], 'canonical', true),
            $this->exercise('Seated Dumbbell Shoulder Press', 'strength', 'load_reps', ['dumbbells'], ['shoulders', 'triceps'], ['seated DB press'], 'canonical', true),
            $this->exercise('Lateral Raise', 'strength', 'load_reps', ['dumbbells', 'cable'], ['side delts'], [], 'canonical', true),
            $this->exercise('Rear Delt Fly', 'strength', 'load_reps', ['dumbbells', 'machine'], ['rear delts'], [], 'canonical', true),
            $this->exercise('Face Pull', 'strength', 'load_reps', ['cable', 'band'], ['rear delts', 'upper back'], [], 'canonical', true),
            $this->exercise('Bulgarian Split Squat', 'strength', 'load_reps', ['dumbbells', 'bodyweight'], ['quads', 'glutes'], ['BSS'], 'canonical', true),
            $this->exercise('Walking Lunge', 'strength', 'load_reps', ['dumbbells', 'bodyweight'], ['quads', 'glutes'], [], 'canonical', true),
            $this->exercise('Reverse Lunge', 'strength', 'load_reps', ['dumbbells', 'bodyweight'], ['quads', 'glutes'], [], 'canonical', true),
            $this->exercise('Pistol Squat', 'calisthenics', 'reps', ['bodyweight'], ['quads', 'glutes'], ['pistols', 'pistol squats']),
            $this->exercise('Weighted Pistol Squat', 'calisthenics', 'load_reps', ['bodyweight', 'dumbbells', 'kettlebell'], ['quads', 'glutes'], ['weighted pistols', 'weighted pistol'], 'variant', true, 'Pistol Squat'),
            $this->exercise('Leg Press', 'strength', 'load_reps', ['machine'], ['quads', 'glutes'], [], 'canonical', true),
            $this->exercise('Leg Extension', 'strength', 'load_reps', ['machine'], ['quads'], [], 'canonical', true),
            $this->exercise('Leg Curl', 'strength', 'load_reps', ['machine'], ['hamstrings'], [], 'canonical', true),
            $this->exercise('Hip Thrust', 'strength', 'load_reps', ['barbell', 'machine'], ['glutes'], [], 'canonical', true),
            // A lone "calves" phrase must resolve deterministically to the leg-press calf
            // press (this catalog's convention), so "calves" is an explicit alias there and
            // stays out of the calf-raise aliases.
            $this->exercise('Standing Calf Raise', 'strength', 'load_reps', ['machine', 'dumbbells'], ['calves'], ['standing calf raises', 'calf raises', 'calf raise'], 'canonical', true),
            $this->exercise('Seated Calf Raise', 'strength', 'load_reps', ['machine'], ['calves'], ['seated calf raises'], 'canonical', true),
            $this->exercise('Calf Press on Leg Press', 'strength', 'load_reps', ['machine'], ['calves'], ['calves', 'calf press', 'calves on leg press', 'leg press calves'], 'canonical', true),
            $this->exercise('Biceps Curl', 'strength', 'load_reps', ['dumbbells', 'barbell'], ['biceps'], [], 'canonical', true),
            $this->exercise('Hammer Curl', 'strength', 'load_reps', ['dumbbells'], ['biceps', 'forearms'], [], 'canonical', true),
            $this->exercise('Wrist Curl', 'strength', 'load_reps', ['dumbbells', 'barbell'], ['forearms'], ['wrist curls', 'forearm curl', 'forearm curls'], 'canonical', true),
            $this->exercise('Reverse Wrist Curl', 'strength', 'load_reps', ['dumbbells', 'barbell'], ['forearms'], ['reverse wrist curls', 'reverse forearm curl', 'reverse forearm curls', 'wrist extension curls'], 'canonical', true),
            $this->exercise('Forearm Rotation', 'strength', 'load_reps', ['dumbbells'], ['forearms'], ['forearm twist', 'forearm twists', 'pronation supination', 'forearm pronation and supination', 'wrist twists'], 'canonical', true),
            $this->exercise('Dead Hang', 'calisthenics', 'hold', ['pull-up bar'], ['forearms', 'lats'], ['bar hang', 'passive hang']),
            $this->exercise('Triceps Pushdown', 'strength', 'load_reps', ['cable'], ['triceps'], [], 'canonical', true),
            $this->exercise('Skull Crusher', 'strength', 'load_reps', ['barbell', 'dumbbells'], ['triceps'], [], 'canonical', true),
            $this->exercise('Goblet Squat', 'strength', 'load_reps', ['kettlebell', 'dumbbell'], ['quads', 'glutes'], [], 'canonical', true),
            $this->exercise('Kettlebell Clean', 'strength', 'load_reps', ['kettlebell'], ['posterior chain', 'shoulders'], [], 'canonical', true),
            $this->exercise('Turkish Get-Up', 'strength', 'load_reps', ['kettlebell', 'dumbbells'], ['full body', 'shoulders'], ['TGU', 'turkish get up', 'turkish get ups', 'turkish getup'], 'canonical', true),
            $this->exercise('Kettlebell Press', 'strength', 'load_reps', ['kettlebell'], ['shoulders', 'triceps'], [], 'canonical', true),
            $this->exercise('Landmine Press', 'strength', 'load_reps', ['barbell', 'landmine'], ['shoulders', 'chest'], [], 'canonical', true),
            $this->exercise('Cable Fly', 'strength', 'load_reps', ['cable'], ['chest'], [], 'canonical', true),
            $this->exercise('Machine Chest Press', 'strength', 'load_reps', ['machine'], ['chest', 'triceps'], [], 'canonical', true),
            $this->exercise('Hack Squat', 'strength', 'load_reps', ['machine'], ['quads'], [], 'canonical', true),
            $this->exercise('Good Morning', 'strength', 'load_reps', ['barbell'], ['hamstrings', 'lower back'], [], 'canonical', true),
            $this->exercise('Single-Arm Cable Row', 'strength', 'load_reps', ['cable'], ['lats', 'upper back'], [], 'variant', true, 'Seated Cable Row'),
            $this->exercise('Dumbbell Pullover', 'strength', 'load_reps', ['dumbbells'], ['lats', 'chest'], [], 'canonical', true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function conditioning(): array
    {
        return [
            $this->exercise('Row Erg', 'conditioning', 'time_distance', ['row erg'], ['cardio', 'posterior chain'], ['rowing machine']),
            $this->exercise('Ski Erg', 'conditioning', 'time_distance', ['ski erg'], ['cardio', 'lats']),
            $this->exercise('Bike Erg', 'conditioning', 'time_distance', ['bike erg'], ['cardio']),
            $this->exercise('Assault Bike', 'conditioning', 'time', ['assault bike'], ['cardio']),
            $this->exercise('Indoor Ride', 'endurance', 'time_distance', ['bike'], ['cardio'], ['spinning', 'indoor cycling', 'spin bike', 'stationary bike']),
            $this->exercise('Run', 'endurance', 'time_distance', ['bodyweight'], ['cardio'], ['running']),
            $this->exercise('Sprint', 'conditioning', 'time_distance', ['bodyweight'], ['cardio', 'hamstrings']),
            $this->exercise('Jump Rope', 'conditioning', 'time', ['jump rope'], ['cardio', 'calves']),
            $this->exercise('Burpee', 'conditioning', 'reps', ['bodyweight'], ['full body']),
            $this->exercise('Burpee Broad Jump', 'conditioning', 'reps', ['bodyweight'], ['full body']),
            $this->exercise('Sled Push', 'conditioning', 'distance', ['sled'], ['quads', 'glutes']),
            $this->exercise('Sled Pull', 'conditioning', 'distance', ['sled'], ['posterior chain']),
            $this->exercise('Farmer Carry', 'conditioning', 'distance', ['dumbbells', 'kettlebells'], ['grip', 'core'], [], 'canonical', true),
            $this->exercise('Sandbag Carry', 'conditioning', 'distance', ['sandbag'], ['full body'], [], 'canonical', true),
            $this->exercise('Kettlebell Swing', 'conditioning', 'load_reps', ['kettlebell'], ['posterior chain'], ['KB swing'], 'canonical', true),
            $this->exercise('Wall Ball', 'conditioning', 'reps', ['medicine ball'], ['full body'], [], 'canonical', true),
            $this->exercise('Battle Rope', 'conditioning', 'time', ['battle rope'], ['shoulders', 'cardio']),
            $this->exercise('Box Jump', 'conditioning', 'reps', ['box'], ['quads', 'glutes']),
            $this->exercise('Medicine Ball Slam', 'conditioning', 'reps', ['medicine ball'], ['full body'], [], 'canonical', true),
            $this->exercise('Treadmill Incline Walk', 'endurance', 'time_distance', ['treadmill'], ['cardio']),
            $this->exercise('Stair Climber', 'conditioning', 'time', ['machine'], ['cardio', 'glutes']),
            $this->exercise('Elliptical', 'endurance', 'time_distance', ['machine'], ['cardio']),
            $this->exercise('Swimming', 'endurance', 'time_distance', ['pool'], ['cardio'], ['swim', 'swam', 'lap swimming', 'swimming laps', 'freestyle swimming', 'pool swim']),
            $this->exercise('Hike', 'endurance', 'time_distance', ['bodyweight'], ['cardio'], ['hiking']),
            $this->exercise('Outdoor Ride', 'endurance', 'time_distance', ['bike'], ['cardio'], ['cycling', 'road bike ride', 'bike ride']),
        ];
    }

    /**
     * @param  list<string>  $equipment
     * @param  list<string>  $primaryMuscles
     * @param  list<string>  $aliases
     * @return array<string, mixed>
     */
    private function exercise(
        string $name,
        string $category,
        string $trackingMode,
        array $equipment,
        array $primaryMuscles,
        array $aliases = [],
        string $granularity = 'canonical',
        bool $externalLoadAllowed = false,
        ?string $parent = null,
    ): array {
        return [
            'name' => $name,
            'aliases' => $aliases,
            'source' => 'seed',
            'category' => $category,
            'granularity' => $granularity,
            'tags' => [$category],
            'primary_muscles' => $primaryMuscles,
            'secondary_muscles' => [],
            'primary_body_area' => $primaryMuscles[0] ?? null,
            'equipment' => $equipment,
            'tracking_mode' => $trackingMode,
            'unilateral' => str_contains(strtolower($name), 'single') || str_contains(strtolower($name), 'one-leg'),
            'bodyweight' => in_array('bodyweight', $equipment, true) || in_array('rings', $equipment, true),
            'external_load_allowed' => $externalLoadAllowed,
            'parent' => $parent,
            'default_variant_policy' => $granularity === 'bucket' ? 'bucket' : 'log_variant',
        ];
    }
}
