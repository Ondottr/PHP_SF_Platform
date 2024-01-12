<?php declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Classes\Helpers;

use App\View\Components\pagination;
use JetBrains\PhpStorm\ArrayShape;

use function count;
use function is_array;

/**
 * Class is used to paginate an iterable object.
 * You can use it to paginate any array or a collection.
 * You can also use it to get the total number of pages.
 *
 * Usage:
 * $ph = new PaginationHelper( Users::findAll(), $messagesPerPage, $page );
 * $users = $ph->paginate();
 * $pages = $ph->getPages();
 *
 * $pages var is used to display the pagination links by the pagination component.
 *
 * Just import the the pagination component to the view with the $pages var and onclickFunction as a parameter.
 *
 * $this->import( {@link pagination::class}, [ 'onclickFunction' => 'changePage' ] )
 */
final class PaginationHelper
{

    /**
     * Store the total number of pages.
     */
    private int $totalPages;


    /**
     * Constructor method.
     *
     * @param iterable $arr             The iterable object to be paginated.
     * @param int      $messagesPerPage The number of items per page.
     * @param int      $page            The current page number.
     */
    public function __construct(
        private readonly iterable $arr,
        public readonly int $messagesPerPage = 10,
        public int $page = 1
    ) {
        // Set the current page to the total pages if the current page number is larger than the total number of pages.
        if ( $this->page > $this->getTotalPages() )
            $this->page = $this->getTotalPages();

    }


    /**
     * Get the total number of pages.
     *
     * @return int The total number of pages.
     */
    public function getTotalPages(): int
    {
        // Calculate the total pages if it has not been set yet.
        if ( isset( $this->totalPages ) === false )
            $this->totalPages = (int)ceil(
                ( is_array( $this->arr ) ? count( $this->arr ) : $this->arr->count() ) / $this->messagesPerPage
            );

        return $this->totalPages;
    }

    /**
     * Get the items of the current page.
     *
     * @return array The items of the current page.
     */
    public function paginate(): array
    {
        // Calculate the start and end indices of the current page.
        $start = ( $this->page - 1 ) * $this->messagesPerPage;
        $end = $start + $this->messagesPerPage;
        $returnArr = [];
        $i = 1;

        // Iterate through the iterable object and only add the items between the start and end indices to the return array.
        foreach ( $this->arr as $key => $item ) {
            if ( $i > $end )
                break;

            if ( $i > $start )
                $returnArr[ $key ] = $item;

            $i++;
        }

        return $returnArr;
    }

    /**
     * Get the page numbers surrounding the current page.
     *
     * @return array The page numbers surrounding the current page.
     */
    #[ArrayShape( ['first' => 'int', 'page2left' => 'int', 'page1left' => 'int', 'current' => 'int', 'page1right' => 'int', 'page2right' => 'int', 'last' => 'int'] )]
    public function getPages(): array
    {
        // Add the first page number if the current page is greater than 3.
        if ( $this->page > 3 )
            $pages['first'] = 1;

        // If there is more than one page, add the page numbers surrounding the current page.
        if ( $this->getTotalPages() > 1 ) {
            if ( $this->page - 2 > 0 )
                $pages['page2left'] = $this->page - 2;


            if ( $this->page - 1 > 0 )
                $pages['page1left'] = $this->page - 1;


            $pages['current'] = $this->page;


            if ( $this->page + 1 <= $this->getTotalPages() )
                $pages['page1right'] = $this->page + 1;


            if ( $this->page + 2 <= $this->getTotalPages() )
                $pages['page2right'] = $this->page + 2;
        }

        // Add the last page number if the current page is less than the total number of pages minus 2.
        if ( $this->page !== ( $tp = $this->getTotalPages() ) && $this->page + 2 < $tp )
            $pages['last'] = $this->getTotalPages();


        return $pages ?? [];
    }

}
