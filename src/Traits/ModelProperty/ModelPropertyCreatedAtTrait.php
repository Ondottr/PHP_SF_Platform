<?php declare( strict_types=1 );

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

namespace PHP_SF\System\Traits\ModelProperty;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use PHP_SF\System\Attributes\Validator\Constraints as Validate;
use PHP_SF\System\Attributes\Validator\TranslatablePropertyName;
use PHP_SF\System\Core\DateTime;

use function is_string;

trait ModelPropertyCreatedAtTrait
{

    /**
     * @ORM\Column(type="datetime", name="created_at", options={"default": "CURRENT_TIMESTAMP"}, nullable=false)
     */
    #[Validate\DateTime]
    #[TranslatablePropertyName( 'created_at_property' )]
    #[ORM\Column( name: 'created_at', type: 'datetime', nullable: false, options: [ 'default' => 'CURRENT_TIMESTAMP'] )]
    protected string|DateTimeInterface $createdAt;


    final public function getCreatedAt(): DateTimeInterface
    {
        if( is_string( $this->createdAt))
            return ($this->createdAt = new DateTime( $this->createdAt ));

        return $this->createdAt;
    }

    final public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }
}
