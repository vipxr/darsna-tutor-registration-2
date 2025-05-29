<?php
/**
 * Darsna Tutor Scheduler
 * 
 * A dedicated class for handling agent schedule management using LatePoint's OsWorkPeriodModel
 */

use OsWorkPeriodModel;  // ensure PHP can resolve the class

final class Darsna_Tutor_Scheduler {

    public function __construct(){
        // make sure LatePoint has registered its models (their init is priority 10)
        add_action('init', [ $this, 'maybe_save_schedule' ], 20);
    }

    public function maybe_save_schedule(){
        // wherever you trigger schedule‐saving, e.g. on form post
        if ( isset($_POST['my_custom_schedule']) ) {
            $agent_id = intval($_POST['agent_id']);
            $schedule = $_POST['my_custom_schedule'];
            $this->set_agent_schedule( $agent_id, $schedule );
        }
    }

    public function set_agent_schedule( int $agent_id, array $schedule ): bool {
        // require at least one day
        if ( empty( $schedule['days'] ) ) {
            error_log("No days selected");
            return false;
        }

        // use the global OsWorkPeriodModel
        if ( ! class_exists('OsWorkPeriodModel') ) {
            error_log("OsWorkPeriodModel not found — LatePoint probably not loaded yet");
            return false;
        }

        try {
            // times to minutes
            $start = $this->time_to_minutes( $schedule['start'] ?? '09:00' );
            $end   = $this->time_to_minutes( $schedule['end']   ?? '17:00' );
            $loc   = $schedule['location_id'] ?? 1;

            // delete old periods
            OsWorkPeriodModel::where('agent_id', $agent_id)->delete();

            // map and re‐insert
            $day_map = ['mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6,'sun'=>7];
            foreach( $schedule['days'] as $d ){
                if ( empty($day_map[$d]) ) {
                    error_log("Skipping invalid day: $d");
                    continue;
                }
                $wp = OsWorkPeriodModel::create([
                    'agent_id'    => $agent_id,
                    'service_id'  => 0,
                    'location_id' => $loc,
                    'week_day'    => $day_map[$d],
                    'start_time'  => $start,
                    'end_time'    => $end,
                    'chain_id'    => wp_generate_uuid4(),
                ]);
                error_log("Inserted work period for day $d: " . print_r($wp->toArray(), true) );
            }

            return true;
        }
        catch (Exception $e){
            error_log("Failed saving schedule: ".$e->getMessage());
            return false;
        }
    }

    private function time_to_minutes(string $hms): int {
        list($h,$m) = explode(':',$hms);
        return intval($h) * 60 + intval($m);
    }
}

// initialize it
new Darsna_Tutor_Scheduler();