<?php

namespace App\Rules\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Packk\Core\Models\Store;

class NewStore extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    protected $loja;

    public function __construct(Store $l)
    {
        $this->loja = $l;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->from('contato@spark.shipp.delivery', 'Shipp')
            ->subject('Cadastro EC Business')
            ->view('emails.novaloja')
            ->with(
                [
                    'loja' => $this->loja
                ]
            );
    }
}
