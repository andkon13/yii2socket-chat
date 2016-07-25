<?php

namespace React\Stream;

use Evenement\EventEmitter;

class WritableStream extends EventEmitter implements WritableStreamInterface
{
    protected $closed = false;

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->close();
    }

    public function write($data)
    {
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
    }

    public function isWritable()
    {
        return !$this->closed;
    }
}
