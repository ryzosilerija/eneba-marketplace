<?php
declare(strict_types=1);

/**
 * Inserts sample data: categories, 38 products, 8 sellers, offers, fictional keys, demo users.
 * Uses a fixed random seed so every run produces the same data.
 * Also generates simple SVG cover/screenshot images into public/images/products/.
 */
final class Seeder
{
    private const CATEGORIES = [
        'action' => 'Action', 'rpg' => 'RPG', 'shooter' => 'Shooter', 'adventure' => 'Adventure',
        'strategy' => 'Strategy', 'sports' => 'Sports', 'racing' => 'Racing', 'indie' => 'Indie',
        'simulation' => 'Simulation', 'gift-cards' => 'Gift Cards',
    ];

    private const SELLERS = [
        // name, country, rating, sales, verified
        ['PixelTrader', 'Lithuania', 99.1, 182340, true],
        ['KeyNest', 'Poland', 98.4, 95211, true],
        ['GameHub Baltic', 'Latvia', 97.2, 40122, true],
        ['LootLane', 'Germany', 96.8, 61877, false],
        ['NordicKeys', 'Sweden', 99.5, 120456, true],
        ['CodeCrate', 'Netherlands', 94.3, 15320, false],
        ['RapidKeys', 'Estonia', 92.7, 8034, false],
        ['StarSeller', 'Spain', 97.9, 33980, true],
    ];

    /**
     * name, category, platform, region, developer, publisher, release date, list price (cents),
     * rating, sales, featured, one-line pitch
     */
    private const PRODUCTS = [
        ['Cyberpunk 2077', 'rpg', 'Steam', 'GLOBAL', 'CD Projekt Red', 'CD Projekt', '2020-12-10', 5999, 4.5, 15420, true, 'An open-world action RPG set in the neon megacity of Night City.'],
        ['Elden Ring', 'rpg', 'Steam', 'GLOBAL', 'FromSoftware', 'Bandai Namco', '2022-02-25', 5999, 4.9, 18230, true, 'A vast dark-fantasy world full of brutal bosses and hidden secrets.'],
        ["Baldur's Gate 3", 'rpg', 'Steam', 'GLOBAL', 'Larian Studios', 'Larian Studios', '2023-08-03', 5999, 4.9, 21004, true, 'A party-based RPG with deep choices, tactical combat and co-op.'],
        ['The Witcher 3: Wild Hunt GOTY', 'rpg', 'GOG', 'GLOBAL', 'CD Projekt Red', 'CD Projekt', '2015-05-19', 4999, 4.8, 12011, false, 'Monster hunting, rich side quests and both expansions included.'],
        ['Red Dead Redemption 2', 'action', 'Rockstar', 'GLOBAL', 'Rockstar Games', 'Rockstar Games', '2019-11-05', 5999, 4.8, 16540, true, 'An epic tale of outlaw life at the end of the Wild West era.'],
        ['Grand Theft Auto V Premium Edition', 'action', 'Rockstar', 'EUROPE', 'Rockstar North', 'Rockstar Games', '2015-04-14', 2999, 4.6, 25110, false, 'The story mode plus the Criminal Enterprise starter pack.'],
        ['Hogwarts Legacy', 'adventure', 'Steam', 'EUROPE', 'Avalanche Software', 'Warner Bros. Games', '2023-02-10', 5999, 4.4, 9870, false, 'Explore a magical school and the wizarding world around it.'],
        ['Hogwarts Legacy (PS5)', 'adventure', 'PlayStation', 'NORTH AMERICA', 'Avalanche Software', 'Warner Bros. Games', '2023-02-10', 6999, 4.4, 4120, false, 'The PlayStation 5 version of the open-world wizarding adventure.'],
        ['Call of Duty: Modern Warfare III', 'shooter', 'Xbox', 'GLOBAL', 'Sledgehammer Games', 'Activision', '2023-11-10', 6999, 3.9, 11200, false, 'Fast-paced campaign, multiplayer and zombies modes.'],
        ['Counter-Strike 2 Prime Status', 'shooter', 'Steam', 'GLOBAL', 'Valve', 'Valve', '2023-09-27', 1499, 4.3, 30215, false, 'Unlock Prime matchmaking, drops and ranked play.'],
        ['DOOM Eternal', 'shooter', 'Steam', 'GLOBAL', 'id Software', 'Bethesda', '2020-03-20', 3999, 4.7, 8450, false, 'Rip and tear through hordes of demons at breakneck speed.'],
        ['Helldivers 2', 'shooter', 'Steam', 'GLOBAL', 'Arrowhead Game Studios', 'Sony Interactive', '2024-02-08', 3999, 4.5, 14330, true, 'Chaotic four-player co-op missions to spread managed democracy.'],
        ['Battlefield 2042', 'shooter', 'EA App', 'GLOBAL', 'DICE', 'Electronic Arts', '2021-11-19', 5999, 3.6, 5320, false, 'Large-scale 128-player battles with dynamic weather.'],
        ['Battlefield 6', 'shooter', 'EA App', 'GLOBAL', 'Battlefield Studios', 'Electronic Arts', '2025-10-10', 6999, 4.3, 9020, true, 'Modern all-out warfare with destructible environments.'],
        ['EA Sports FC 25', 'sports', 'EA App', 'GLOBAL', 'EA Vancouver', 'Electronic Arts', '2024-09-27', 6999, 3.8, 12870, false, 'Club football with Ultimate Team, career mode and Rush.'],
        ['EA Sports FC 25 (PS5)', 'sports', 'PlayStation', 'EUROPE', 'EA Vancouver', 'Electronic Arts', '2024-09-27', 7999, 3.8, 7450, false, 'The PlayStation 5 edition of the football sim.'],
        ['NBA 2K25', 'sports', 'Steam', 'GLOBAL', 'Visual Concepts', '2K', '2024-09-06', 5999, 3.7, 4010, false, 'Basketball simulation with MyCAREER and MyTEAM modes.'],
        ['Forza Horizon 5', 'racing', 'Xbox', 'GLOBAL', 'Playground Games', 'Xbox Game Studios', '2021-11-09', 5999, 4.7, 13300, false, 'Open-world racing festival across the landscapes of Mexico.'],
        ['F1 24', 'racing', 'Steam', 'EUROPE', 'Codemasters', 'Electronic Arts', '2024-05-31', 5999, 3.9, 3870, false, 'The official Formula 1 game with a reworked career mode.'],
        ['Gran Turismo 7', 'racing', 'PlayStation', 'EUROPE', 'Polyphony Digital', 'Sony Interactive', '2022-03-04', 6999, 4.5, 6120, false, 'A celebration of car culture with hundreds of cars.'],
        ["Sid Meier's Civilization VI", 'strategy', 'Steam', 'GLOBAL', 'Firaxis Games', '2K', '2016-10-21', 5999, 4.6, 10250, false, 'Build an empire to stand the test of time, one more turn at a time.'],
        ['Age of Empires IV', 'strategy', 'Steam', 'GLOBAL', 'Relic Entertainment', 'Xbox Game Studios', '2021-10-28', 3999, 4.4, 5340, false, 'Classic real-time strategy across historic civilizations.'],
        ['Hades II', 'indie', 'Steam', 'GLOBAL', 'Supergiant Games', 'Supergiant Games', '2025-09-25', 2999, 4.9, 11870, true, 'A roguelike descent against the Titan of Time.'],
        ['Hollow Knight', 'indie', 'Steam', 'GLOBAL', 'Team Cherry', 'Team Cherry', '2017-02-24', 1499, 4.9, 17640, false, 'A hand-drawn metroidvania set in a ruined insect kingdom.'],
        ['Stardew Valley', 'simulation', 'Steam', 'GLOBAL', 'ConcernedApe', 'ConcernedApe', '2016-02-26', 1399, 4.9, 22410, false, 'Inherit a farm, grow crops and become part of a small town.'],
        ['Celeste', 'indie', 'Nintendo', 'EUROPE', 'Maddy Makes Games', 'Maddy Makes Games', '2018-01-25', 1999, 4.8, 3550, false, 'A tight, heartfelt platformer about climbing a mountain.'],
        ['The Legend of Zelda: Tears of the Kingdom', 'adventure', 'Nintendo', 'EUROPE', 'Nintendo EPD', 'Nintendo', '2023-05-12', 6999, 4.9, 8870, true, 'Build, fly and explore across the skies and depths of Hyrule.'],
        ['Super Mario Bros. Wonder', 'adventure', 'Nintendo', 'NORTH AMERICA', 'Nintendo EPD', 'Nintendo', '2023-10-20', 5999, 4.8, 6430, false, 'A colourful 2D platformer full of surprising Wonder effects.'],
        ['Microsoft Flight Simulator 2024', 'simulation', 'Xbox', 'GLOBAL', 'Asobo Studio', 'Xbox Game Studios', '2024-11-19', 6999, 4.0, 3210, false, 'Fly anywhere on a detailed recreation of the whole planet.'],
        ['Cities: Skylines II', 'simulation', 'Steam', 'GLOBAL', 'Colossal Order', 'Paradox Interactive', '2023-10-24', 4999, 3.5, 4560, false, 'Design and manage a living, growing city.'],
        ["Assassin's Creed Shadows", 'action', 'Ubisoft Connect', 'EUROPE', 'Ubisoft Quebec', 'Ubisoft', '2025-03-20', 6999, 4.1, 5980, false, 'Two assassins, one story, set in feudal Japan.'],
        ['Starfield', 'rpg', 'Xbox', 'GLOBAL', 'Bethesda Game Studios', 'Bethesda', '2023-09-06', 6999, 3.7, 7100, false, 'A space RPG with hundreds of planets to explore.'],
        ['Black Myth: Wukong', 'action', 'Steam', 'GLOBAL', 'Game Science', 'Game Science', '2024-08-20', 5999, 4.6, 13560, true, 'A mythological action RPG inspired by Journey to the West.'],
        ['Monster Hunter Wilds', 'action', 'Steam', 'GLOBAL', 'Capcom', 'Capcom', '2025-02-28', 6999, 4.2, 9230, false, 'Hunt colossal monsters across living, changing ecosystems.'],
        ['Steam Gift Card 20 EUR', 'gift-cards', 'Steam', 'EUROPE', null, 'Valve', null, 2000, 4.9, 28900, false, 'Add 20 EUR to your Steam Wallet.'],
        ['PlayStation Store Gift Card 50 EUR', 'gift-cards', 'PlayStation', 'EUROPE', null, 'Sony Interactive', null, 5000, 4.8, 14300, false, 'Top up your PlayStation Store wallet with 50 EUR.'],
        ['Xbox Game Pass Ultimate 3 Months', 'gift-cards', 'Xbox', 'GLOBAL', null, 'Microsoft', null, 5499, 4.7, 19800, true, 'Three months of Game Pass on console, PC and cloud.'],
        ['Nintendo eShop Card 25 EUR', 'gift-cards', 'Nintendo', 'EUROPE', null, 'Nintendo', null, 2500, 4.8, 7600, false, 'Add 25 EUR to your Nintendo eShop account.'],
    ];

    /** $writeImages=false is used by the tests (the SVGs only need to be generated once). */
    public function __construct(private PDO $pdo, private bool $writeImages = true)
    {
    }

    public function run(callable $log): void
    {
        mt_srand(42); // deterministic sample data

        $catIds = [];
        $stmt = $this->pdo->prepare('INSERT INTO categories (name, slug) VALUES (?, ?) RETURNING id');
        foreach (self::CATEGORIES as $slug => $name) {
            $stmt->execute([$name, $slug]);
            $catIds[$slug] = (int) $stmt->fetchColumn();
        }

        $sellerIds = [];
        $stmt = $this->pdo->prepare('INSERT INTO sellers (name, slug, country, rating, sales_count, is_verified) VALUES (?, ?, ?, ?, ?, ?) RETURNING id');
        foreach (self::SELLERS as [$name, $country, $rating, $sales, $verified]) {
            $stmt->execute([$name, self::slugify($name), $country, $rating, $sales, $verified ? 'true' : 'false']);
            $sellerIds[] = (int) $stmt->fetchColumn();
        }

        $productStmt = $this->pdo->prepare(
            'INSERT INTO products (slug, name, description, category_id, platform, region, developer, publisher, release_date,
                                   original_price, rating, sales_count, is_featured, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id'
        );
        $imageStmt = $this->pdo->prepare('INSERT INTO product_images (product_id, url, position) VALUES (?, ?, ?)');
        $offerStmt = $this->pdo->prepare('INSERT INTO offers (product_id, seller_id, price) VALUES (?, ?, ?) RETURNING id');
        $keyStmt = $this->pdo->prepare('INSERT INTO digital_keys (offer_id, code) VALUES (?, ?)');

        $imageDir = dirname(__DIR__) . '/public/images/products';
        if (!is_dir($imageDir)) {
            mkdir($imageDir, 0775, true);
        }

        $offerCount = 0;
        $keyCount = 0;
        foreach (self::PRODUCTS as $i => $p) {
            [$name, $cat, $platform, $region, $dev, $pub, $release, $price, $rating, $sales, $featured, $pitch] = $p;
            $slug = self::slugify($name . ' ' . $platform . ' ' . $region);

            $productStmt->execute([
                $slug, $name, self::description($name, $pitch, $platform, $region, $cat),
                $catIds[$cat], $platform, $region, $dev, $pub, $release,
                $price, $rating, $sales, $featured ? 'true' : 'false',
                // spread "created_at" so the catalog has a natural "newest listings" order
                date('Y-m-d H:i:s', strtotime('-' . (count(self::PRODUCTS) - $i) . ' days')),
            ]);
            $productId = (int) $productStmt->fetchColumn();

            // 1 cover + 2 "screenshots"
            for ($n = 0; $n < 3; $n++) {
                $file = "$slug-$n.svg";
                if ($this->writeImages) file_put_contents("$imageDir/$file", $n === 0 ? self::coverSvg($name, $platform, $region) : self::screenshotSvg($name, $n));
                $imageStmt->execute([$productId, "/images/products/$file", $n]);
            }

            // 1-4 sellers per product, each with their own price (55%..95% of list price)
            $sellerPick = $sellerIds;
            shuffle($sellerPick);
            $numOffers = mt_rand(1, 4);
            for ($o = 0; $o < $numOffers; $o++) {
                $offerPrice = (int) (round($price * mt_rand(55, 95) / 100 / 50) * 50 - 1); // ends in .49 / .99
                $offerStmt->execute([$productId, $sellerPick[$o], max(99, $offerPrice)]);
                $offerId = (int) $offerStmt->fetchColumn();
                $offerCount++;

                // Some offers are deliberately out of stock (0 keys) to show that state in the UI.
                $keys = mt_rand(1, 10) === 1 ? 0 : mt_rand(3, 12);
                for ($k = 0; $k < $keys; $k++) {
                    $keyStmt->execute([$offerId, self::fakeKey()]);
                    $keyCount++;
                }
            }
        }

        // Demo users (password: password123)
        $userStmt = $this->pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
        foreach ([['Demo User', 'demo@keyvault.test'], ['Alice Tester', 'alice@keyvault.test']] as [$uName, $email]) {
            $userStmt->execute([$uName, $email, password_hash('password123', PASSWORD_DEFAULT)]);
        }

        $log(sprintf('Seeded %d categories, %d sellers, %d products, %d offers, %d keys, 2 users.',
            count($catIds), count($sellerIds), count(self::PRODUCTS), $offerCount, $keyCount));
    }

    /** Fictional, clearly-fake test key, e.g. KVTEST-3F9A1-C02BD-77E1A */
    public static function fakeKey(): string
    {
        $part = fn () => strtoupper(bin2hex(random_bytes(3)));
        return 'KVTEST-' . substr($part(), 0, 5) . '-' . substr($part(), 0, 5) . '-' . substr($part(), 0, 5);
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s);
        return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    }

    private static function description(string $name, string $pitch, string $platform, string $region, string $cat): string
    {
        if ($cat === 'gift-cards') {
            return "$pitch\n\nThis is a digital code delivered instantly to your account after payment. "
                . "Redeem it on $platform. Region: $region – make sure your account region matches before buying.";
        }
        return "$pitch\n\nYou are buying a digital $platform key for $name. "
            . "The key is shown in your account right after a successful payment and can be activated on $platform. "
            . ($region === 'GLOBAL'
                ? 'This key works worldwide.'
                : "Region: $region – this key can only be activated in the $region region.");
    }

    /** Hue derived from the name so each product gets its own stable colour scheme. */
    private static function hue(string $name, int $offset = 0): int
    {
        return (crc32($name) + $offset) % 360;
    }

    private static function coverSvg(string $name, string $platform, string $region): string
    {
        $h1 = self::hue($name);
        $h2 = ($h1 + 60) % 360;
        $lines = explode("\n", wordwrap(htmlspecialchars($name, ENT_XML1), 16, "\n", true));
        $text = '';
        foreach ($lines as $i => $line) {
            $y = 250 + $i * 40 - (count($lines) - 1) * 20;
            $text .= "<text x=\"30\" y=\"$y\" font-family=\"Arial, Helvetica, sans-serif\" font-size=\"34\" font-weight=\"700\" fill=\"#fff\">$line</text>";
        }
        $platform = htmlspecialchars($platform, ENT_XML1);
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="400" height="530" viewBox="0 0 400 530">
<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
<stop offset="0" stop-color="hsl($h1,70%,45%)"/><stop offset="1" stop-color="hsl($h2,75%,18%)"/></linearGradient></defs>
<rect width="400" height="530" fill="url(#g)"/>
<circle cx="320" cy="90" r="120" fill="hsl($h2,80%,60%)" opacity=".25"/>
<circle cx="60" cy="470" r="160" fill="hsl($h1,80%,70%)" opacity=".15"/>
$text
<rect x="30" y="455" rx="6" width="150" height="34" fill="rgba(0,0,0,.35)"/>
<text x="42" y="478" font-family="Arial, Helvetica, sans-serif" font-size="16" fill="#fff">$platform · $region</text>
</svg>
SVG;
    }

    private static function screenshotSvg(string $name, int $n): string
    {
        $h = self::hue($name, $n * 97);
        $shapes = '';
        for ($i = 0; $i < 7; $i++) {
            $x = (crc32($name . $n . $i) % 800);
            $y = (crc32($i . $name . $n) % 450);
            $r = 30 + (crc32("$i$n") % 120);
            $shapes .= "<circle cx=\"$x\" cy=\"$y\" r=\"$r\" fill=\"hsl(" . (($h + $i * 25) % 360) . ",70%,55%)\" opacity=\".35\"/>";
        }
        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="450" viewBox="0 0 800 450">
<rect width="800" height="450" fill="hsl($h,45%,14%)"/>$shapes
<rect y="380" width="800" height="70" fill="rgba(0,0,0,.4)"/>
<text x="30" y="424" font-family="Arial, Helvetica, sans-serif" font-size="24" fill="#fff">Screenshot $n</text>
</svg>
SVG;
    }
}
