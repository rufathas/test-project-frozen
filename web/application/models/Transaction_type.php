<?php


namespace Model;


class Transaction_type
{
    const WALLET_BALANCE_WITHDRAW = 'withdrawal from wallet balance';
    const WALLET_BALANCE_REPLENISHMENT = 'wallet balance replenishment';
    const LIKE_BALANCE_WITHDRAW = 'withdrawal from like balance';
    const LIKE_BALANCE_REPLENISHMENT = 'like balance replenishment';
}