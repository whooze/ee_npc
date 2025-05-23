<?php
/**
 * Country Preferences Class
 *
 * PHP Version 7
 *
 * @category Classes
 * @package  EENPC
 * @author   
 * @license  All EENPC files are under the MIT License
 * @link     https://github.com/jhaagsma/ee_npc
 */

namespace EENPC;

// FUTURE: remove and/or everywhere

class cpref
{

    public function __construct($server, $cpref_file, $cnum, $rules)
    {
        $this->cnum = $cnum;

        // useful stuff from $server - FUTURE: don't do this?
        $this->reset_start_time = $server->reset_start;
        $this->reset_end_time = $server->reset_end;
        $this->is_clan_server = $server->is_clan_server;
        $this->server_turn_rate = $server->turn_rate;      

        // ******************************************************************
        // REMINDER: add new things to get_static_prefs_to_print() as needed!
        // ******************************************************************

        // FUTURE: load from personality file
        $this->strat = $cpref_file->strat;
        // $this->is_country_playable = (log_translate_simple_strat_name($this->strat) == "UNKNOWN" ? false : true);
        $this->bot_secret_number = $cpref_file->bot_secret;
        $this->playrand = $cpref_file->playrand;
        $this->price_tolerance = $cpref_file->price_tolerance;
        $this->acquire_ingame_allies = ($this->is_clan_server && !$server->is_cooperation_server ? $cpref_file->allyup : false); // no allies on non-clan servers for now
        $this->gdi = $cpref_file->gdi;
        // end personality file issue

        $turns_in_set = ($this->reset_end_time - $this->reset_start_time) / $this->server_turn_rate;
        // general        
        $this->mass_explore_stop_acreage_rep = ($this->is_clan_server ? 99999 : 10000);
        $this->mass_explore_stop_acreage_non_rep = ($this->is_clan_server ? 99999 : 8200);


        $number_of_seconds_in_set = $this->reset_end_time - $this->reset_start_time;
        $this->techer_land_goal = 11 * ($cnum % 100) + ($turns_in_set < 2200 ? 7500 : 10000); // FUTURE - this is very basic, also weird to only set for techer
         // weird to set only for techer
        $this->techer_round_explore_cutoff_percentage = $this->get_techer_round_explore_cutoff_percentage();
        $this->techer_allowed_to_explore = (time() < $this->techer_round_explore_cutoff_percentage * $number_of_seconds_in_set + $this->reset_start_time) ? true : false;

        $this->base_inherent_value_for_tech = 325 * $rules->nw_per_tech_point;
        // if tpt is high enough, spend this percentage of turns teching before considering exploring
        $this->min_perc_teching_turns = $this->get_min_perc_teching_turns();
        $this->initial_bpt_target = ($this->strat == "T" ? 60 : 65);

        // FUTURE: spal
        // FUTURE: landgoal

        // buying
        $this->purchase_schedule_number = $this->get_purchase_schedule_number();
        $this->min_land_to_buy_defense = $this->get_min_land_to_buy_defense();        
        $this->target_cash_after_stockpiling = ($this->strat == "C" || $this->strat == "I" ? 1200000000 : 1500000000);
        $this->spend_extra_money_cooldown_turns = ($this->strat == "C" ? 5 : 7);
        $this->max_stockpiling_loss_percent = 70; // must be > 0
        $this->max_bushel_buy_price_with_low_stored_turns = 99;
        $this->tech_max_purchase_price = 10000;     
        // techers probably need to buy everything off PM, but other strats can likely skip turrets
        $this->final_dpnw_for_stocking_calcs = ($this->strat == "T" ? (2025 / 6.5) : (3*144+588+2.5*192)/(1.5+2+0.6*2.5));
        $this->should_demo_attempt_bushel_recycle = true;

        // selling
        $this->production_algorithm = $this->get_production_algorithm();  

        $number_of_days_in_set = ($this->reset_end_time - $this->reset_start_time) / 86400;
        $min_hours_to_look_back = floor(min(72, max(1, $number_of_days_in_set * 1 / 5))); // express would be 1, 30 day reset is 6, 60 day is 12 hours
        $max_hours_to_look_back = floor(min(72, $number_of_days_in_set * 8 / 5)); // express would be 8, 30 day reset is 48, 60 day is 72 hours
        $this->market_search_look_back_hours = mt_rand($min_hours_to_look_back, $max_hours_to_look_back); 

        // destocking uses shorter look backs
        $min_hours_to_look_back = floor(min(3, max(1, $number_of_days_in_set * 1 / 5))); // express would be 1, 30 day reset is 3, 60 day is 3 hours
        $max_hours_to_look_back = floor(min(8, max(2, $number_of_days_in_set * 3 / 5))); // express would be 3, 30 day reset is 8, 60 day is 8 hours
        $this->market_search_look_back_hours_DESTOCK = mt_rand($min_hours_to_look_back, $max_hours_to_look_back); 

        $this->chance_to_sell_based_on_avg_price = 50; 
        $this->chance_to_sell_based_on_current_price = 50;
        $this->chance_to_resell_military_based_on_avg_price_DESTOCK = 30; 
        $this->chance_to_resell_military_based_on_current_price_DESTOCK = 30;
        $this->chance_to_resell_military_based_on_high_price_DESTOCK = 30;
        $this->chance_to_resell_military_based_on_price_limit_DESTOCK = 10;

        $this->selling_price_max_distance = 15; // 15 means a country may sell up to 15% over or under market prices
        $this->selling_price_std_dev = 5;
        $this->farmer_max_early_sell_price = 49;
        $this->farmer_max_late_sell_price = 99;
        $this->indy_min_profit_for_public_sale = 1.35;

        // destocking
        $this->earliest_destocking_start_time = $this->get_earliest_destocking_start_time();

        // VALIDATION
        if($this->chance_to_sell_based_on_avg_price + $this->chance_to_sell_based_on_current_price <> 100)
            log_error_message(123, $cnum, "chance_to_sell options must add to 100");

        if($this->chance_to_resell_military_based_on_avg_price_DESTOCK + $this->chance_to_resell_military_based_on_current_price_DESTOCK + $this->chance_to_resell_military_based_on_high_price_DESTOCK + $this->chance_to_resell_military_based_on_price_limit_DESTOCK <> 100)
            log_error_message(123, $cnum, "chance_to_sell_DESTOCK options must add to 100");           
    }//end __construct()


    public function get_static_prefs_to_print() {
        $static_prefs = [    
              "strat"
            , "initial_bpt_target"
            , "bot_secret_number"
            , "playrand"
            , "acquire_ingame_allies"
            , "gdi"
            , "mass_explore_stop_acreage_rep"
            , "mass_explore_stop_acreage_non_rep"
            , "techer_land_goal"
            , "techer_round_explore_cutoff_percentage"
            , "techer_allowed_to_explore"
            , "base_inherent_value_for_tech"
            , "purchase_schedule_number"
            , "min_land_to_buy_defense"
            , "target_cash_after_stockpiling"
            , "spend_extra_money_cooldown_turns"
            , "max_bushel_buy_price_with_low_stored_turns"
            , "tech_max_purchase_price"
            , "max_stockpiling_loss_percent"
            , "final_dpnw_for_stocking_calcs"
            , "should_demo_attempt_bushel_recycle"
            , "chance_to_sell_based_on_avg_price"
            , "chance_to_sell_based_on_current_price"
            , "selling_price_max_distance"
            , "selling_price_std_dev"
            , "chance_to_resell_military_based_on_avg_price_DESTOCK"
            , "chance_to_resell_military_based_on_current_price_DESTOCK"
            , "chance_to_resell_military_based_on_high_price_DESTOCK"
            , "chance_to_resell_military_based_on_price_limit_DESTOCK"
            , "earliest_destocking_start_time"
        ];

        if($this->strat == 'T' || $this->strat == 'I') {
            $static_prefs[] = "production_algorithm";
        }

        if($this->strat == 'T') {
            $static_prefs[] = "techer_land_goal";
            $static_prefs[] = "min_perc_teching_turns";
        }

        if($this->strat == 'F') {
            $static_prefs[] = "farmer_max_early_sell_price";
            $static_prefs[] = "farmer_max_late_sell_price";        
        }

        return $static_prefs;
    }


    public function get_market_history_look_back_hours() {
        return (time() < $this->earliest_destocking_start_time? $this->market_search_look_back_hours : $this->market_search_look_back_hours_DESTOCK);
    }


    public function get_sell_price_method($is_destocking = false) {
        $rand = mt_rand(1, 100);

        if($is_destocking) {
            if($rand <= $this->chance_to_resell_military_based_on_avg_price_DESTOCK)
                return "AVG";
            elseif($rand <= $this->chance_to_resell_military_based_on_avg_price_DESTOCK + $this->chance_to_resell_military_based_on_current_price_DESTOCK)
                return "CURRENT";   
            elseif($rand <= $this->chance_to_resell_military_based_on_avg_price_DESTOCK + $this->chance_to_resell_military_based_on_current_price_DESTOCK + $this->chance_to_resell_military_based_on_high_price_DESTOCK)
                return "HIGH";
            else
                return "LIMIT";
        }
        else {
            if($rand <= $this->chance_to_sell_based_on_avg_price)
                return "AVG";
            else
                return "CURRENT";     
        }
    }



    private function get_min_land_to_buy_defense() {
       return 1000 + round($this->decode_bot_secret(4) / 5);
    }


    private function get_min_perc_teching_turns() {
         // between 20% and 50%, fine if not completely even probabilities on edges
        return 20 + round($this->decode_bot_secret(3) / 33);
    }


    private function get_techer_round_explore_cutoff_percentage() {
        // between 40% and 60%, fine if not completely even probabilities on edges
       return round(0.01 * (40 + $this->decode_bot_secret(5) / 5000), 2);
    } 



    private function get_production_algorithm() {
        $schedule_rand = $this->decode_bot_secret(2);

        if($schedule_rand <= 19) // this sounds good in theory, but it's a positive feedback loop
            return "SALES";
        elseif($schedule_rand <= 59)
            return "AVG_PRICE";
        elseif($schedule_rand <= 69)
            return "HIGH_PRICE";
        elseif($schedule_rand <= 89)
            return "CURRENT_PRICE";
        else
            return "RANDOM";
    }

    private function get_purchase_schedule_number() {
        if($this->strat == "I" || $this->strat == "T" || $this->strat == "R")
            return 0;
        $schedule_rand = $this->decode_bot_secret(1);

        // FUTURE: use names
        if($schedule_rand <= 1)
            return 0; // heavy military
        elseif($schedule_rand <= 3)
            return 1; // heavy tech
        elseif($schedule_rand <= 6)
            return 2;// favor military
        else
            return 3;// favor tech
    }


    // use to get a random number specific to each cnum that doesn't change during the reset
    // up to 9 digits supported
    public function decode_bot_secret($desired_digits) {
        if ($desired_digits > 9) {
            log_error_message(999, $this->cnum, 'decode_bot_secret() with invalid $desired_digits');
            return 0;
        }
        return $this->bot_secret_number % pow(10, $desired_digits);
    }



    /*
    NAME: get_earliest_possible_destocking_start_time_for_country
    PURPOSE: calculates the earliest time that a country can start destocking based on strategy, time in reset, and other things
    RETURNS: the earliest time        
    */
    private function get_earliest_destocking_start_time() {
        // I just made this up, can't say that the ranges are any good - Slagpit 20210316
        // techer is last 80% to 92.5% of reset
        // rainbow and indy are last 90% to 95% of reset
        // farmer and casher are last 95% to 98.5% of reset	
        // note: TURNS_TO_PASS_BEFORE_NEXT_DESTOCK_ATTEMPT value should allow for at least two executions for all strategies

        $country_specific_interval_wait = 0.001 * $this->decode_bot_secret(3); // random number three digit number between 0.000 and 0.999 that's fixed for each country

        // changes to here must be reflected also in calculate_next_play_in_seconds()
        switch ($this->strat) {
            case 'F':
                $window_start_time_factor = 0.95;
                $window_end_time_factor = 0.985;
                $country_specific_interval_wait = 0; // farmer window is too short to use the random factor
                break;
            case 'T':
                $window_start_time_factor = 0.80;
                $window_end_time_factor = 0.925;			
                break;
            case 'C':
                $window_start_time_factor = 0.95;
                $window_end_time_factor = 0.985;
                $country_specific_interval_wait = 0; // casher window is too short to use the random factor
                break;
            case 'I':
                $window_start_time_factor = 0.90;
                $window_end_time_factor = 0.95;
                break;
            default:
                $window_start_time_factor = 0.90;
                $window_end_time_factor = 0.975;
        }

        $number_of_seconds_in_set = $this->reset_end_time - $this->reset_start_time;
        $number_of_seconds_in_window = ($window_end_time_factor - $window_start_time_factor) * $number_of_seconds_in_set;
        
        // example of what we're doing here: suppose that start time factor is 90%, end time is 95% and interval wait is 0.25
        // the earliest destock time then is after 25% has passed of the interval starting with 90% of the reset and ending with 95% of the reset
        // so for a 100 day reset, this country should start destocking after 92.5 days have passed
        return $this->reset_start_time + $window_start_time_factor * $number_of_seconds_in_set + $country_specific_interval_wait * $number_of_seconds_in_window;
    }

    public function calculate_next_play_in_seconds($nexttime, $exit_condition, $rules, $dynamic_country_settings) {
        // split for unit testing purposes
        return $this->internal_calculate_next_play_in_seconds($this->cnum, $exit_condition, $nexttime, $this->strat, $this->is_clan_server, $rules->max_time_to_market, $rules->max_possible_market_sell, $this->playrand, $this->reset_start_time, $this->reset_end_time, $this->server_turn_rate, $dynamic_country_settings->lastTurns, $rules->maxturns, $dynamic_country_settings->turnsStored, $rules->maxstore);
    }


    private function internal_calculate_next_play_in_seconds($cnum, $exit_condition, $nexttime, $strat, $is_clan_server, $max_time_to_market, $max_possible_market_sell, $country_play_rand_factor, $server_reset_start, $server_reset_end, $server_turn_rate, $country_turns_left, $server_max_turns, $country_stored_turns, $server_stored_turns) {
        if($nexttime <> null) {
            log_country_message($cnum, "Next play seconds was passed in as $nexttime");
            return $nexttime; // always return $nexttime if it's passed on
        }

        if($exit_condition <> null) {
            if($exit_condition == 'WAIT_FOR_PUBLIC_MARKET_FOOD') {
                log_country_message($cnum, "Country is while waiting for cheaper public market food to appear, so set login to 4 turns away");
                return 4 * $server_turn_rate;
            }
            elseif($exit_condition == 'LOW_TURNS_PLAYED') {
                log_country_message($cnum, "Country played a low number of turns, so set next login to 30 min + 50% of max time to market");
                return ceil(1800 + 0.5 * $max_time_to_market);
            }
            elseif($exit_condition <> 'NORMAL' && $exit_condition <> 'ERROR')
                log_error_message(999, $cnum, "internal_calculate_next_play_in_seconds(): invalid value for exit condition: $exit_condition");
        }
    
        /*
        indy start standard window: ($max_possible_market_sell/25) * (1 hour + max market time + 3 turns)
        indy end standard window: ($max_possible_market_sell/25) * ( 1 hour + max market time + 27 turns)
        express range is 3 hours to 5.85 hours
        alliance range is 7 hours to 15 hours
    
        techer start standard window: ($max_possible_market_sell/25) * (1 hour + max market time + 15 turns)
        techer end standard window: ($max_possible_market_sell/25) * (1 hour + max market time + 39 turns)
        express range is 4 hours to 7.29 hours
        alliance range is 11 hours to 19 hours
    
        casher start standard window: 0.3 * ($server_max_turns * $server_turn_rate)
        casher end standard window: 0.9 * ($server_max_turns * $server_turn_rate)
        express range is 7.2 hours to 21.6 hours
        alliance range is 12 hours to 36 hours
    
        rainbow start standard window: 0.3 * ($server_max_turns * $server_turn_rate)
        rainbow end standard window: 0.9 * ($server_max_turns * $server_turn_rate)
        express range is 7.2 hours to 21.6 hours
        alliance range is 12 hours to 36 hours
    
        farmer start standard window: 3 hours + max market time
        farmer end standard window: 0.6 * ($server_max_turns * $server_turn_rate)
        express range is 3.45 hours to 12 hours
        alliance range is 8.45 hours to 24 hours
        */
        switch($strat) {
            case 'F':
                $play_seconds_minimum = $max_time_to_market + 3 * 3600; // FUTURE: this isn't a good value for servers with very fast turn rates
                $play_seconds_maximum = round(0.6 * $server_turn_rate * $server_max_turns);
    
                if ($play_seconds_minimum > $play_seconds_maximum) // won't happen with any current servers, but just in case
                    $play_seconds_minimum = round(0.3 * $server_turn_rate * $server_max_turns);
                break;
            case 'T':
                $play_seconds_minimum = round(($max_possible_market_sell / 25) * (3600 + $max_time_to_market + 15 * $server_turn_rate));
                $play_seconds_maximum = round(($max_possible_market_sell / 25) * (3600 + $max_time_to_market + 39 * $server_turn_rate));
                break;
            case 'C':
                $play_seconds_minimum = round(0.3 * $server_turn_rate * $server_max_turns);
                //$play_seconds_maximum = round(0.9 * $server_turn_rate * $server_max_turns); // FUTURE - change to this once express bot count and market stuff is fixed
                $play_seconds_maximum = round(0.6 * $server_turn_rate * $server_max_turns);
                break;
            case 'I':
                $play_seconds_minimum = round(($max_possible_market_sell / 25) * (3600 + $max_time_to_market + 3 * $server_turn_rate));
                $play_seconds_maximum = round(($max_possible_market_sell / 25) * (3600 + $max_time_to_market + 27 * $server_turn_rate));
                break;
            default:
                $play_seconds_minimum = round(0.3 * $server_turn_rate * $server_max_turns);
                // $play_seconds_maximum = round(0.9 * $server_turn_rate * $server_max_turns); // FUTURE - change to this once express bot count and market stuff is fixed
                $play_seconds_maximum = round(0.6 * $server_turn_rate * $server_max_turns);
        }
    
        log_country_message($cnum, "For strategy ".Bots::txtStrat($cnum).", min play seconds is $play_seconds_minimum and max play seconds is $play_seconds_maximum");
    
        // shrink the window up to 25% based on the country's preference for play
        // $country_play_rand_factor is random number in range (1, 2)
        $seconds_to_subtract_from_max = round(0.25 * ($country_play_rand_factor - 1) * ($play_seconds_maximum - $play_seconds_minimum));
        log_country_message($cnum, "Country playing activity preference is $country_play_rand_factor, so adjusting max down by $seconds_to_subtract_from_max seconds");
        $play_seconds_maximum -= $seconds_to_subtract_from_max;
    
        $std_dev = round($play_seconds_maximum - $play_seconds_minimum) / 4; // 2.5% chance of min and max values
        $bell_random_seconds = round(Math::purebell($play_seconds_minimum, $play_seconds_maximum, $std_dev));
        log_country_message($cnum, "Bell random play seconds calculated as $bell_random_seconds");
    
        // if the next play time would mean that additional turns start going into storage, adjust it forward
        $free_turns = $server_max_turns - $country_turns_left;
        $depleted_stored_turns = round(min(0.5 * $free_turns, $country_stored_turns));
        $approx_seconds_until_new_turns_go_to_stored = $server_turn_rate * ($free_turns - $depleted_stored_turns);
        log_country_message($cnum, "Server max onhand turns is $server_max_turns, country turns left is $country_turns_left, country stored turns is $country_stored_turns");
        log_country_message($cnum, "It will take approximately $approx_seconds_until_new_turns_go_to_stored seconds until new turns go into storage");   
     
        $seconds_until_next_play = $bell_random_seconds;
        if ($approx_seconds_until_new_turns_go_to_stored < $bell_random_seconds) {
            $seconds_until_next_play = $approx_seconds_until_new_turns_go_to_stored;
            log_country_message($cnum, "Bell random seconds value would result in additional stored turns, so changing play time to $seconds_until_next_play");
        }
    
        // don't allow bots to login more frequently than 4 * turn rate under normal conditions
        if (4 * $server_turn_rate > $seconds_until_next_play) { 
            log_country_message($cnum, "$seconds_until_next_play is less than 4 times the turn rate, so adjusting down");
            $seconds_until_next_play = 4 * $server_turn_rate;        
        }
    
        // if next play time is after 99.5% of the reset is done, country might miss its chance to destock
        // the destocking code always sets next play, so we'll never get here if the country already started destocking
        $seconds_in_reset = $server_reset_end - $server_reset_start;
        // FUTURE: get magic numbers from a destocking.php function
        if (time() + $seconds_until_next_play > $server_reset_start + 0.995 * $seconds_in_reset) {
            $target_play_time_range_start = $server_reset_start + 0.985 * $seconds_in_reset;
            $target_play_time_range_end = $server_reset_start + 0.995 * $seconds_in_reset;
            $seconds_between_targets = $target_play_time_range_end - $target_play_time_range_start;
    
            log_country_message($cnum, "Previous calculated value of $seconds_until_next_play is too close to the end of the set");
     
            // random range between 98.5% and 99.5% of reset
            $seconds_until_next_play = ($target_play_time_range_start + 0.01 * mt_rand(0, 100) * $seconds_between_targets) - time();
    
            if ($seconds_until_next_play <= 0) {
                $seconds_until_next_play = 1800; // not sure how we could get here, but set it to half an hour
                log_error_message(124, $cnum, '$seconds_until_next_play was calculated as below 0');
            }
            log_country_message($cnum, "Next play changed to $seconds_until_next_play to allow for destocking");
        }
        
        log_country_message($cnum, "The final value for seconds to next play is: $seconds_until_next_play");
    
        return $seconds_until_next_play;
    }    


}