<?php

namespace App\Services;

use Hashids\Hashids;

class IdCodec
{
    private Hashids $hashids;

    public function __construct()
    {
        $salt = config('app.id_codec_salt') ?: config('app.key');
        $this->hashids = new Hashids($salt, 8);
    }

    public function encode(int $id): string
    {
        return $this->hashids->encode($id);
    }

    public function decode(string $hash): int
    {
        $ids = $this->hashids->decode($hash);
        abort_unless(count($ids) === 1, 404);

        return (int) $ids[0];
    }
}
