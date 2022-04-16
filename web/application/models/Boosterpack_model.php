<?php
namespace Model;

use App;
use Exception;
use http\Exception\RuntimeException;
use System\Emerald\Emerald_model;
use stdClass;
use ShadowIgniterException;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 27.01.2020
 * Time: 10:10
 */
class Boosterpack_model extends Emerald_model
{
    const CLASS_TABLE = 'boosterpack';

    /** @var float Цена бустерпака */
    protected $price;
    /** @var float Банк, который наполняется  */
    protected $bank;
    /** @var float Наша комиссия */
    protected $us;

    protected $boosterpack_info;


    /** @var string */
    protected $time_created;
    /** @var string */
    protected $time_updated;

    /**
     * @return float
     */
    public function get_price(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     *
     * @return bool
     */
    public function set_price(int $price):bool
    {
        $this->price = $price;
        return $this->save('price', $price);
    }

    /**
     * @return float
     */
    public function get_bank(): float
    {
        return $this->bank;
    }

    /**
     * @param float $bank
     *
     * @return bool
     */
    public function set_bank(float $bank):bool
    {
        $this->bank = $bank;
        return $this->save('bank', $bank);
    }

    /**
     * @return float
     */
    public function get_us(): float
    {
        return $this->us;
    }

    /**
     * @param float $us
     *
     * @return bool
     */
    public function set_us(float $us):bool
    {
        $this->us = $us;
        return $this->save('us', $us);
    }

    /**
     * @return string
     */
    public function get_time_created(): string
    {
        return $this->time_created;
    }

    /**
     * @param string $time_created
     *
     * @return bool
     */
    public function set_time_created(string $time_created):bool
    {
        $this->time_created = $time_created;
        return $this->save('time_created', $time_created);
    }

    /**
     * @return string
     */
    public function get_time_updated(): string
    {
        return $this->time_updated;
    }

    /**
     * @param string $time_updated
     *
     * @return bool
     */
    public function set_time_updated(string $time_updated):bool
    {
        $this->time_updated = $time_updated;
        return $this->save('time_updated', $time_updated);
    }

    //////GENERATE

    /**
     * @return Boosterpack_info_model[]
     */
    public function get_boosterpack_info(): array
    {
        return Boosterpack_info_model::get_by_boosterpack_id($this->get_id());
    }

    function __construct($id = NULL)
    {
        parent::__construct();

        $this->set_id($id);
    }

    public function reload()
    {
        parent::reload();
        return $this;
    }

    public static function create(array $data)
    {
        App::get_s()->from(self::CLASS_TABLE)->insert($data)->execute();
        return new static(App::get_s()->get_insert_id());
    }

    public function delete():bool
    {
        $this->is_loaded(TRUE);
        App::get_s()->from(self::CLASS_TABLE)->where(['id' => $this->get_id()])->delete()->execute();
        return App::get_s()->is_affected();
    }

    public static function get_all()
    {
        return static::transform_many(App::get_s()->from(self::CLASS_TABLE)->many());
    }

    /**
     * @return int
     * @throws Exception
     */
    public function open(): int
    {
        // start transaction
        App::get_s()->set_transaction_repeatable_read()->execute();
        App::get_s()->start_trans()->execute();

        try {
            $user = User_model::get_user();

            //check that the user has enough funds
            if ($user->get_wallet_balance() < $this->get_price()) {
                App::get_s()->rollback()->execute();
                return 0;
            }
            $remove_money = $user->remove_money($this->price);

            $max_price = $this->get_bank() + $this->get_price() - $this->get_us();

            // take random boosterpack
            $item = $this->get_contains($max_price);

            // set new data in the profit bank
            $set_bank_result = $this->set_bank($this->get_bank() + $this->get_price() - $this->get_us() - $item->price);
            $set_like_result = $user->set_likes_balance($user->get_likes_balance() + $item->price);

            if ($remove_money && $set_bank_result && $set_like_result && App::get_s()->is_affected()) {
                Analytics_model::info_log('boosterpack',Transaction_type::WALLET_BALANCE_WITHDRAW,$this->price,$this->get_id());
                Analytics_model::info_log('boosterpack',Transaction_type::LIKE_BALANCE_REPLENISHMENT,$item->price,$this->get_id());
                App::get_s()->commit()->execute();
                return $item->price;
            }

            App::get_s()->rollback()->execute();
            return 0;
        } catch (RuntimeException $e) {
            App::get_s()->rollback()->execute();
            return 0;
        }
    }

    /**
     * @param int $max_available_likes
     *
     * @return stdClass
     */
    public function get_contains(int $max_available_likes): stdClass
    {
        // find max win point
        $item_data = Item_model::find_boosterpack_price($max_available_likes);
        // take random boosterpack from array, then convert this to object
        return $this->_array_to_object_convert($item_data[array_rand($item_data)]);
    }

    private function _array_to_object_convert($array): stdClass
    {
        $o = new stdClass();
        foreach ($array as $key => $value) {
            $o->$key = $value;
        }
        return $o;
    }
    /**
     * @param Boosterpack_model $data
     * @param string            $preparation
     *
     * @return stdClass|stdClass[]
     */
    public static function preparation(Boosterpack_model $data, string $preparation = 'default')
    {
        switch ($preparation)
        {
            case 'default':
                return self::_preparation_default($data);
            default:
                throw new Exception('undefined preparation type');
        }
    }

    /**
     * @param Boosterpack_model $data
     *
     * @return stdClass
     */
    private static function _preparation_default(Boosterpack_model $data): stdClass
    {
        $o = new stdClass();

        $o->id = $data->get_id();
        $o->price = $data->get_price();

        return $o;
    }
}
