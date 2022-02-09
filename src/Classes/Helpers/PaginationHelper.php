<?php declare(strict_types=1);
/*
 * Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
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

final class PaginationHelper
{

    private const MESSAGES_PER_PAGE = 10;


    private int $end;

    private int $page;

    private int $start;

    private int $totalPages;

    private int $messagesPerPage;


    public function __construct(
        private iterable $arr,
        int              $messagesPerPage = self::MESSAGES_PER_PAGE,
        int              $page = null
    )
    {
        $this->setTotalPages($messagesPerPage);

        $this->setPage($page);

        if ($this->getPage() > $this->getTotalPages())
            $this->setPage($this->getTotalPages());

    }

    private function setTotalPages(int $messagesPerPage): void
    {
        $this->setMessagesPerPage($messagesPerPage);

        $totalMessages = (is_array($this->arr)) ? count($this->arr) : $this->arr->count();


        $this->totalPages = (int)ceil($totalMessages / $this->getMessagesPerPage());
    }

    private function setMessagesPerPage(int $messagesPerPage): void
    {
        $this->messagesPerPage = $messagesPerPage;
    }

    private function getMessagesPerPage(): int
    {
        return $this->messagesPerPage;
    }

    private function setPage(int $page): void
    {
        $this->page = $page;

        if ($this->page > $this->getTotalPages())
            $this->page = $this->getTotalPages();

    }


    private function getTotalPages(): int
    {
        return $this->totalPages;
    }

    private function getPage(): int
    {
        return $this->page;
    }

    public function paginate(): array
    {
        $i = 1;
        foreach ($this->arr as $key => $item) {
            if ($i <= $this->getStart()) {
                $i++;
                continue;
            }
            if ($i > $this->getEnd())
                break;


            $returnArr[$key] = $item;

            $i++;
        }

        return $returnArr ?? [];
    }

    private function getStart(): int
    {
        if (!isset($this->start))
            $this->setStart();

        return $this->start;
    }

    private function setStart(): void
    {
        $this->start = $this->getPage() * $this->getMessagesPerPage() - $this->getMessagesPerPage();
    }

    private function getEnd(): int
    {
        if (!isset($this->end))
            $this->setEnd();

        return $this->end;
    }

    private function setEnd(): void
    {
        if (!isset($this->start))
            $this->setStart();

        $this->end = $this->getStart() + $this->getMessagesPerPage();
    }

    public function getPages(): array
    {
        if ($this->getPage() > 3)
            $pages['first'] = 1;


        if ($this->getTotalPages() > 1) {

            if ($this->getPage() - 2 > 0)
                $pages['page2left'] = $this->getPage() - 2;


            if ($this->getPage() - 1 > 0)
                $pages['page1left'] = $this->getPage() - 1;


            $pages['current'] = $this->getPage();


            if ($this->getPage() + 1 <= $this->getTotalPages())
                $pages['page1right'] = $this->getPage() + 1;


            if ($this->getPage() + 2 <= $this->getTotalPages())
                $pages['page2right'] = $this->getPage() + 2;

        }

        if ($this->getPage() !== ($tp = $this->getTotalPages()) && $this->getPage() + 2 < $tp)
            $pages['last'] = $this->getTotalPages();


        return $pages ?? [];
    }

}
