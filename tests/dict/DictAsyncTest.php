<?hh // strict
/*
 *  Copyright (c) 2004-present, Facebook, Inc.
 *  All rights reserved.
 *
 *  This source code is licensed under the MIT license found in the
 *  LICENSE file in the root directory of this source tree.
 *
 */

use namespace HH\Lib\{Dict, Str, Vec};
use function Facebook\FBExpect\expect; // @oss-enable
use type Facebook\HackTest\{DataProvider, HackTest}; // @oss-enable

// @oss-disable: <<Oncalls('hack')>>
final class DictAsyncTest extends HackTest {

  public static function provideTestGen(): varray<mixed> {
    return varray[
      tuple(
        Vector {
          async {return 'the';},
          async {return 'quick';},
          async {return 'brown';},
          async {return 'fox';},
        },
        dict[
          0 => 'the',
          1 => 'quick',
          2 => 'brown',
          3 => 'fox',
        ],
      ),
      tuple(
        Map {
          'foo' => async {return 1;},
          'bar' => async {return 2;},
        },
        dict[
          'foo' => 1,
          'bar' => 2,
        ],
      ),
      tuple(
        HackLibTestTraversables::getKeyedIterator(darray[
          'foo' => async {return 1;},
          'bar' => async {return 2;},
        ]),
        dict[
          'foo' => 1,
          'bar' => 2,
        ],
      ),
    ];
  }

  <<DataProvider('provideTestGen')>>
  public function testFromAsync<Tk as arraykey, Tv>(
    KeyedTraversable<Tk, Awaitable<Tv>> $awaitables,
    dict<Tk, Tv> $expected,
  ): void {
    /* HH_IGNORE_ERROR[5542] open source */
    \HH\Asio\join(async {
      $actual = await Dict\from_async($awaitables);
      expect($actual)->toBeSame($expected);
    });
  }

  public static function provideTestGenFromKeys(): varray<mixed> {
    return varray[
      tuple(
        Vector {'the', 'quick', 'brown', 'fox'},
        async ($word) ==> Str\length($word),
        dict[
          'the' => 3,
          'quick' => 5,
          'brown' => 5,
          'fox' => 3,
        ],
      ),
      tuple(
        HackLibTestTraversables::getIterator(
          varray['the', 'quick', 'brown', 'fox'],
        ),
        async ($word) ==> Str\length($word),
        dict[
          'the' => 3,
          'quick' => 5,
          'brown' => 5,
          'fox' => 3,
        ],
      ),
    ];
  }

  <<DataProvider('provideTestGenFromKeys')>>
  public function testFromKeysAsync<Tk as arraykey, Tv>(
    Traversable<Tk> $keys,
    (function(Tk): Awaitable<Tv>) $async_func,
    dict<Tk, Tv> $expected,
  ): void {
    /* HH_IGNORE_ERROR[5542] open source */
    \HH\Asio\join(async {
      $actual = await Dict\from_keys_async($keys, $async_func);
      expect($actual)->toBeSame($expected);
    });
  }

  public function testFromKeysDuplicateKeysAsync(): void {
    /* HH_IGNORE_ERROR[5542] open source */
    \HH\Asio\join(async {
      // Like Ref<int>, but not a flibism
      $run_cnt = Map { 'value' => 0 };
      $actual = await Dict\from_keys_async(
        vec[1, 1, 2],
        async ($k) ==> {
          ++$run_cnt['value'];
          return $k;
        },
      );
      expect($actual)->toBeSame(dict[1 => 1, 2 => 2]);
      expect($run_cnt['value'])->toBeSame(2);
    });
  }

  public static function provideTestGenFilter(): varray<mixed> {
    return varray[
      tuple(
        darray[
          2 => 'two',
          4 => 'four',
          6 => 'six',
          8 => 'eight',
        ],
        async ($word) ==> Str\length($word) % 2 === 1,
        dict[
          2 => 'two',
          6 => 'six',
          8 => 'eight',
        ],
      ),
      tuple(
        dict[
          '2' => 'two',
          '4' => 'four',
          6 => 'six',
          '8' => 'eight',
        ],
        async ($word) ==> Str\length($word) % 2 === 1,
        dict[
          '2' => 'two',
          6 => 'six',
          '8' => 'eight',
        ],
      ),
      tuple(
        Vector {'the', 'quick', 'brown', 'fox', 'jumped', 'over'},
        async ($word) ==> Str\length($word) % 2 === 0,
        dict[
          4 => 'jumped',
          5 => 'over',
        ],
      ),
    ];
  }

  <<DataProvider('provideTestGenFilter')>>
  public function testFilterAsync<Tk as arraykey, Tv>(
    KeyedContainer<Tk, Tv> $traversable,
    (function(Tv): Awaitable<bool>) $value_predicate,
    dict<Tk, Tv> $expected,
  ): void {
    /* HH_IGNORE_ERROR[5542] open source */
    \HH\Asio\join(async {
      $actual = await Dict\filter_async($traversable, $value_predicate);
      expect($actual)->toBeSame($expected);
    });
  }

  public static function provideTestGenFilterWithKey(): varray<mixed> {
    return varray[
      tuple(
        darray[
          2 => 'two',
          4 => 'four',
          6 => 'six',
          8 => 'eight',
        ],
        async ($_key, $word) ==> Str\length($word) % 2 === 1,
        dict[
          2 => 'two',
          6 => 'six',
          8 => 'eight',
        ],
      ),
      tuple(
        dict[
          '2' => 'two',
          '4' => 'four',
          6 => 'six',
          '8' => 'eight',
        ],
        async ($key, $word) ==> Str\length($word) % 2 === 1 && $key === '2',
        dict[
          '2' => 'two',
        ],
      ),
      tuple(
        Vector {'the', 'quick', 'brown', 'fox', 'jumped', 'over'},
        async ($_key, $word) ==> Str\length($word) % 2 === 0,
        dict[
          4 => 'jumped',
          5 => 'over',
        ],
      ),
    ];
  }

  <<DataProvider('provideTestGenFilterWithKey')>>
  public async function testFilterWithKeyAsync<Tk as arraykey, Tv>(
    KeyedContainer<Tk, Tv> $container,
    (function(Tk, Tv): Awaitable<bool>) $predicate,
    dict<Tk, Tv> $expected,
  ): Awaitable<void> {
    $actual = await Dict\filter_with_key_async(
      $container,
      $predicate,
    );
    expect($actual)->toBeSame($expected);
  }

  public static function provideTestGenMap(): varray<mixed> {
    return varray[
      tuple(
        varray[],
        $x ==> $x,
        dict[],
      ),
      tuple(
        Map {
          'one' => 1,
          'two' => 2,
          'three' => 3,
        },
        async ($n) ==> $n * $n,
        dict[
          'one' => 1,
          'two' => 4,
          'three' => 9,
        ],
      ),
      tuple(
        Vector {'the', 'quick', 'brown', 'fox'},
        async ($word) ==> Str\reverse($word),
        dict[
          0 => 'eht',
          1 => 'kciuq',
          2 => 'nworb',
          3 => 'xof',
        ],
      ),
      tuple(
        HackLibTestTraversables::getIterator(
          varray['the', 'quick', 'brown', 'fox'],
        ),
        async ($word) ==> Str\reverse($word),
        dict[
          0 => 'eht',
          1 => 'kciuq',
          2 => 'nworb',
          3 => 'xof',
        ],
      ),
    ];
  }

  <<DataProvider('provideTestGenMap')>>
  public function testMapAsync<Tk as arraykey, Tv1, Tv2>(
    KeyedTraversable<Tk, Tv1> $traversable,
    (function(Tv1): Awaitable<Tv2>) $value_func,
    dict<Tk, Tv2> $expected,
  ): void {
    /* HH_IGNORE_ERROR[5542] open source */
    \HH\Asio\join(async {
      $actual = await Dict\map_async($traversable, $value_func);
      expect($actual)->toBeSame($expected);
    });
  }

  public static function provideTestGenMapWithKey(): varray<mixed> {
    return varray[
      tuple(
        varray[],
        async ($a, $b) ==> null,
        dict[],
      ),
      tuple(
        vec[1, 2, 3],
        async ($k, $v) ==> (string)$k.$v,
        dict[0 => '01', 1 => '12', 2 => '23'],
      ),
      tuple(
        Vector {'the', 'quick', 'brown', 'fox'},
        async ($k, $v) ==> (string)$k.$v,
        dict[
          0 => '0the',
          1 => '1quick',
          2 => '2brown',
          3 => '3fox',
        ],
      ),
      tuple(
        HackLibTestTraversables::getKeyedIterator(Vec\range(1, 5)),
        async ($k, $v) ==> $k * $v,
        dict[
          0 => 0,
          1 => 2,
          2 => 6,
          3 => 12,
          4 => 20,
        ],
      ),
    ];
  }

  <<DataProvider('provideTestGenMapWithKey')>>
  public function testMapWithKeyAsync<Tk as arraykey, Tv1, Tv2>(
    KeyedTraversable<Tk, Tv1> $traversable,
    (function(Tk, Tv1): Awaitable<Tv2>) $value_func,
    dict<Tk, Tv2> $expected,
  ): void {
    /* HH_IGNORE_ERROR[5542] open source */
    \HH\Asio\join(async {
      $result = await Dict\map_with_key_async($traversable, $value_func);
      expect($result)->toBeSame($expected);
    });
  }
}
