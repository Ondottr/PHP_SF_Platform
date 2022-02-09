<?php declare( strict_types=1 );

namespace Symfony\Component\HttpFoundation\Session\Storage\Proxy;

use SessionHandlerInterface;
use const PHP_SESSION_ACTIVE;


abstract class AbstractProxy
{
    /**
     * Flag if handler wraps an internal PHP session handler (using {@see SessionHandler}).
     */
    protected bool $wrapper = false;

    protected string $saveHandlerName;

    /**
     * Gets the session.save_handler name.
     */
    public function getSaveHandlerName(): ?string
    {
        return $this->saveHandlerName;
    }

    /**
     * Is this proxy handler and instance of {@see SessionHandlerInterface}.
     */
    public function isSessionHandlerInterface(): bool
    {
        return $this instanceof SessionHandlerInterface;
    }

    /**
     * Returns true if this handler wraps an internal PHP session save handler using {@see SessionHandler}.
     */
    public function isWrapper(): bool
    {
        return $this->wrapper;
    }

    /**
     * Gets the session ID.
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * Sets the session ID.
     * Abort session if already started
     */
    public function setId( string $id ): void
    {
        if ( $this->isActive() )
            session_abort();

        session_id( $id );
    }

    /**
     * Has a session started?
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Gets the session name.
     */
    public function getName(): string
    {
        return session_name();
    }

    /**
     * Sets the session name.
     * Abort session if already started
     */
    public function setName( string $name ): void
    {
        if ( $this->isActive() )
            session_abort();

        session_name( $name );
    }

}
