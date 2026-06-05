<?php
namespace App\Services;
use Hashids\Hashids;
class IdCodec {private Hashids $hashids;public function __construct(){ $this->hashids=new Hashids(config('app.key'),8); }public function encode(int $id):string{return $this->hashids->encode($id);}public function decode(string $hash):int{$ids=$this->hashids->decode($hash);abort_unless(count($ids)===1,404);return (int)$ids[0];}}