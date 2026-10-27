<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the forms accept: sign-up, passwords, products, and the search word.
 */
final class ValidationTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function signUp(): array
    {
        return [
            'email' => 'Ane@Example.com',
            'firstname' => 'Ane',
            'lastname' => 'Etxeberria',
            'city' => 'Eibar',
            'stateProv' => 'Gipuzkoa',
            'country' => 'Euskal Herria',
            'postcode' => '20600',
            'telephone' => '600 00 00 00',
            'password' => 'blue capsule at nine',
            'password2' => 'blue capsule at nine',
        ];
    }

    public function testAGoodSignUp(): void
    {
        [$data, $errors] = validate_registration($this->signUp());

        self::assertSame([], $errors);
        self::assertSame('ane@example.com', $data['email']);
        self::assertSame('600000000', $data['telephone']);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function badSignUps(): iterable
    {
        yield 'not an email' => ['email', 'ane', 'email'];
        yield 'empty name' => ['firstname', '   ', 'firstname'];
        yield 'a line break in a name' => ['lastname', "Etxe\nberria", 'lastname'];
        yield 'a name too long' => ['city', str_repeat('a', 61), 'city'];
        yield 'postcode with letters' => ['postcode', '2060A', 'postcode'];
        yield 'postcode too short' => ['postcode', '2060', 'postcode'];
        yield 'phone too short' => ['telephone', '12345', 'telephone'];
        yield 'phone with letters' => ['telephone', '600abc000', 'telephone'];
    }

    #[DataProvider('badSignUps')]
    public function testABadFieldIsNamed(string $field, string $value, string $errorOn): void
    {
        [, $errors] = validate_registration([$field => $value] + $this->signUp());

        self::assertArrayHasKey($errorOn, $errors);
    }

    public function testAFieldSentAsAnArrayIsEmptyNotAnError(): void
    {
        [$data, $errors] = validate_registration(['firstname' => ['x']] + $this->signUp());

        self::assertSame('', $data['firstname']);
        self::assertSame('Eremu hau bete behar da.', $errors['firstname']);
    }

    public function testPasswords(): void
    {
        self::assertNull(password_problem('blue capsule at nine', 'blue capsule at nine'));
        self::assertStringContainsString('12', (string) password_problem('short', 'short'));
        self::assertSame('Pasahitzak ez datoz bat.', password_problem('blue capsule at nine', 'blue capsule at ten'));
        self::assertNotNull(password_problem(str_repeat('a', 129), str_repeat('a', 129)));
        self::assertNotNull(password_problem('ane@example.com', 'ane@example.com', 'Ane@Example.com'));
    }

    public function testASignUpPasswordIsCheckedToo(): void
    {
        [, $errors] = validate_registration(['password' => 'short', 'password2' => 'short'] + $this->signUp());

        self::assertArrayHasKey('password', $errors);
    }

    public function testPrices(): void
    {
        self::assertSame('12.00', parse_price('12'));
        self::assertSame('12.50', parse_price('12,5'));
        self::assertSame('0.99', parse_price(' 0.99 '));
        self::assertNull(parse_price(''));
        self::assertNull(parse_price('-1'));
        self::assertNull(parse_price('12.999'));
        self::assertNull(parse_price('100000'));
        self::assertNull(parse_price('12 OR 1=1'));
    }

    public function testANewProduct(): void
    {
        [$product, $errors] = validate_new_product(['izena' => 'Ibuprofenoa', 'deskripzioa' => '400 mg', 'salneurria' => '3,95', 'stock' => '10']);

        self::assertSame([], $errors);
        self::assertSame(['izena' => 'Ibuprofenoa', 'deskripzioa' => '400 mg', 'salneurria' => '3.95', 'stock' => 10], $product);
    }

    public function testABadNewProductNamesEveryField(): void
    {
        [, $errors] = validate_new_product(['izena' => '', 'deskripzioa' => str_repeat('x', 151), 'salneurria' => 'free', 'stock' => '-3']);

        self::assertSame(['izena', 'deskripzioa', 'salneurria', 'stock'], array_keys($errors));
    }

    public function testAnUpdateWithoutAPriceKeepsTheOldOne(): void
    {
        [$update, $errors] = validate_product_update(['deskripzioa' => "Lerro bat\nbeste bat", 'salneurria' => '']);

        self::assertSame([], $errors);
        self::assertNull($update['salneurria']);
    }

    public function testAnUpdateWithABadPrice(): void
    {
        [, $errors] = validate_product_update(['deskripzioa' => 'ondo', 'salneurria' => '9; DROP TABLE produktuak']);

        self::assertArrayHasKey('salneurria', $errors);
    }

    public function testTheSearchWordsWildcardsAreLiteral(): void
    {
        self::assertSame('%produktu%', like_pattern('produktu'));
        self::assertSame('%50!%%', like_pattern('50%'));
        self::assertSame('%a!_b%', like_pattern('a_b'));
        self::assertSame('%!!%', like_pattern('!'));
    }

    public function testEmails(): void
    {
        self::assertTrue(is_email('ane@example.com'));
        self::assertFalse(is_email('ane@'));
        self::assertFalse(is_email(str_repeat('a', 250) . '@example.com'));
    }
}
