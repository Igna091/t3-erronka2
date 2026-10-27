<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * index.php shows only the pages on the list, each to the visitors allowed to see it.
 */
final class RoutesTest extends TestCase
{
    public function testNoActionIsTheCatalogue(): void
    {
        self::assertSame('main.php', route_for(null)['file'] ?? null);
        self::assertSame('main.php', route_for('')['file'] ?? null);
    }

    public function testAKnownPage(): void
    {
        self::assertSame(['file' => 'updel.php', 'access' => 'admin'], route_for('updel'));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function notPages(): iterable
    {
        yield 'unknown' => ['nothing'];
        yield 'a file name' => ['conf'];
        yield 'a path' => ['../conf'];
        yield 'an absolute path' => ['/etc/passwd'];
        yield 'a URL' => ['http://example.com/x'];
        yield 'a page name with more after it' => ['login.php'];
        yield 'an array' => [['login']];
    }

    #[DataProvider('notPages')]
    public function testAnythingElseIsNotAPage(mixed $action): void
    {
        self::assertNull(route_for($action));
    }

    public function testWhoMayOpenWhat(): void
    {
        $guest = null;
        $user = ['is_admin' => false];
        $admin = ['is_admin' => true];

        foreach (ROUTES as $action => $route) {
            $expected = match ($route['access']) {
                'public' => [true, true, true],
                'user' => [false, true, true],
                'admin' => [false, false, true],
            };
            self::assertSame($expected, [may_open($route, $guest), may_open($route, $user), may_open($route, $admin)], $action);
        }
    }

    public function testTheAdminPagesAreAdminOnly(): void
    {
        self::assertSame('admin', ROUTES['description']['access']);
        self::assertSame('admin', ROUTES['updel']['access']);
        self::assertSame('user', ROUTES['account']['access']);
    }
}
