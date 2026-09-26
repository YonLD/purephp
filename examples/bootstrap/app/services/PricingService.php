<?php declare(strict_types=1);

require_once __DIR__ . '/../dao/PricingDao.php';

/**
 * The domain layer of the pricing page: it turns the records of PricingDao
 * into the props of one component, so a component fetches its own slice here
 * instead of receiving it through the page.
 */
final class PricingService
{
    /**
     * The company name of the page header; the links come from the DAO.
     */
    private const COMPANY = 'Company name';

    /**
     * The page header: the company name, the nav links and the sign-up link.
     *
     * @return array{
     *     company: string,
     *     navs: list<array{text: string, href: string, class: string}>,
     *     signUp: array{text: string, href: string, class: string}
     * }
     */
    public static function header(): array
    {
        $header = PricingDao::content()['header'];

        return [
            'company' => self::COMPANY,
            'navs' => $header['navs'],
            'signUp' => $header['signUp'],
        ];
    }

    /**
     * The pricing heading: the title and the description.
     *
     * @return array{title: string, desc: string}
     */
    public static function pricing(): array
    {
        return PricingDao::content()['pricing'];
    }

    /**
     * The pricing cards.
     *
     * @return list<array{type: string, price: string, features: list<string>, text: string, class: string}>
     */
    public static function deck(): array
    {
        return PricingDao::content()['deck']['cards'];
    }

    /**
     * The page footer: the logo column and the link columns.
     *
     * @return array{
     *     logo: array{src: string, width: string, height: string, text: string},
     *     columns: list<array{title: string, links: list<array{text: string, href: string}>}>
     * }
     */
    public static function footer(): array
    {
        $footer = PricingDao::content()['footer'];

        return [
            'logo' => $footer['logo'],
            'columns' => $footer['links'],
        ];
    }
}
