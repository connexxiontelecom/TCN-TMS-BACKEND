<?php

namespace App\Models;

use App\Models\Year;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Constraint\Count;
use function PHPUnit\Framework\isEmpty;

class TrainingSchedule extends Model
{
    public function year(){
        return $this->belongsTo(Year::class, 'ts_year');
    }

    public function createdBy(){
        return $this->belongsTo(Employee::class, 'ts_created_by');
    }

    public function department(){
        return $this->belongsTo(Department::class, 'ts_year');
    }

    public function approvedBy(){
        return $this->belongsTo(Employee::class, 'ts_approved_by');
    }

    public function files(){
        return $this->hasMany(File::class, 'files_training_id', 'id');
    }

    public function nominees(){
         return $this->hasMany(Nomination::class, 'nm_training_id', 'id');

    }
    public function approvals($id){
       // return $this->hasMany(schedule_approval::class, 'schedule_id', 'id');
        $approvals =  DB::table('schedule_approvals')
            ->join('employees', 'employees.id', '=', 'schedule_approvals.approver_id')
            ->select('employees.id as e_id', 'employees.*', 'schedule_approvals.*')
            ->where(function ($query) use($id){
                $query->where('schedule_approvals.schedule_id', '=', $id);
            })->get();

      return $approvals;

    }


    public function createSchedule(array $parameters){

        $ts= new TrainingSchedule();
        $ts->ts_title = $parameters['Title'];
        $ts->ts_description = $parameters['Description'];
        $ts->ts_type = $parameters['TypeofTraining'];
        $ts->ts_department = $parameters['Department'];
        $ts->ts_start = $parameters['Start'];
        $ts->ts_end = $parameters['End'];
        $ts->ts_cost = $parameters['Cost'];
        $ts->ts_facilitator = $parameters['Facilitator'];
        $ts->ts_created_by = 2;//logged in user
        $ts->ts_status = 1;//not submitted //2 submitted/pending 3//canceled //4 approved
        $year  = new Year();
        $yr = $year->createYear(date('Y'));
        $ts->ts_year = $yr->id;//date('Y');//current year
        $ts->save();
        return true;
    }


    public function updateSchedule(array $parameters){
        $ts = TrainingSchedule::find($parameters['id']);
        $ts->ts_title = $parameters['Title'];
        $ts->ts_description = $parameters['Description'];
        $ts->ts_type = $parameters['TypeofTraining'];
        $ts->ts_department = $parameters['Department'];
        $ts->ts_start = $parameters['Start'];
        $ts->ts_end = $parameters['End'];
        $ts->ts_cost = $parameters['Cost'];
        $ts->ts_facilitator = $parameters['Facilitator'];
        $ts->save();
        return true;
    }


    public function getSchedules($period){

        if($period!=0){
            return TrainingSchedule::where('ts_year', $period)->get();
        }
        else{
            return TrainingSchedule::all();
        }
    }


    public function getSchedule($id){

        $schedule = TrainingSchedule::where('id', $id)->first();
        if(!isEmpty($schedule))
        {
            $schedule->createdBy;
            $schedule->year;
            $schedule->department;
            $schedule->files;
        }
        return $schedule;
    }


    public function getApprovedSchedules($period){

        if($period!=0){
            //return TrainingSchedule::where('ts_year', $period)->where("ts_status", 4)->get();
            return TrainingSchedule::where(function ($query) use ($period) {
                $query->where('ts_year', $period)->where("ts_status", 4);
            })->oRwhere(function ($query) use ($period) {
                $query->where('ts_year', $period)->where("ts_status", 5);
            })->get();
        }
        else{
            return TrainingSchedule::where('ts_status', 4)->oRWhere('ts_status', 5)->get();
        }
    }

    public function getSubmittedSchedules($period){

        if($period!=0){
            return TrainingSchedule::where('ts_year', $period)->where("ts_status", "!=", 1)->get();
        }
        else{
            return TrainingSchedule::where("ts_status", "!=", 1)->get();
        }
    }

    public function approveSchedule($parameters){

        //check if all approvers have approved
        // by comparing total approver and total number approvals for this schedule
        // if the last approval is a declined mark the scheduled declined
        // else proceed to the next
        $sch_approvl = new schedule_approval();
        $total_approvers = $sch_approvl->getTotalApprovers();
        $user = $parameters['user'];
        foreach ( json_decode($parameters["schedules"]) as $param)
        {
            $sch_approvl->approveSchedule($param,$user);
            $last_approval =  $sch_approvl->getLastApproval($param);
            $approvals =  $sch_approvl->getScheduleApprovals($param);

            //last approval is declined
            // mark schedule finally as declined
            if($last_approval->status == 3){
                $schedule =  TrainingSchedule::find($param);
                $schedule->ts_status = 3;// declined
                $schedule->ts_approved_by = $user;
                $schedule->ts_approved_on = date('Y-m-d H:i:s');
                $schedule->save();
            }
            else{
                if($total_approvers == \count($approvals)){
                    $schedule =  TrainingSchedule::find($param);
                    $schedule->ts_status = 4; //approved
                    $schedule->ts_approved_by = $user;
                    $schedule->ts_approved_on = date('Y-m-d H:i:s');
                    $schedule->save();
                }
                else{
                    $schedule =  TrainingSchedule::find($param);
                    $schedule->ts_status = 2; //pending - in progress
                    $schedule->ts_approved_by = $user;
                    $schedule->ts_approved_on = date('Y-m-d H:i:s');
                    $schedule->save();
                }
            }



           /* $schedule =  TrainingSchedule::find($param);
            $schedule->ts_status = 4;
            $schedule->ts_approved_by = 2;
            $schedule->ts_approved_on = date('Y-m-d H:i:s');
            $schedule->save();*/
        }
        return $this->getSubmittedSchedules(0);
    }

    public function getCompletedSchedules($period){
        if ($period!=0)
        {
            return TrainingSchedule::where("ts_status",  5)->where("ts_year", $period)->get();
        }
        else{
            return TrainingSchedule::where("ts_status",  5)->get();
        }

    }


    public function declineSchedule($parameters){
        $sch_approvl = new schedule_approval();
        $user = $parameters['user'];
        foreach ( json_decode($parameters["schedules"]) as $param)
        {
            $sch_approvl->declineSchedule($param, $user);
            $schedule =  TrainingSchedule::find($param);
            $schedule->ts_status = 3;
            $schedule->ts_approved_by = $user;
            $schedule->ts_approved_on = date('Y-m-d H:i:s');
            $schedule->save();
        }
        return $this->getSubmittedSchedules(0);
    }



    public function submitSchedule($parameters){
        foreach ( json_decode($parameters["schedules"]) as $param)
        {
            $schedule =  TrainingSchedule::find($param);
            $schedule->ts_status = 2;
            $schedule->save();
       }
       return $this->getSchedules(0);
    }

    public function createApprovedTraining(array $parameters){
        $ts= new TrainingSchedule();
        $ts->ts_title = $parameters['Title'];
        $ts->ts_description = $parameters['Description'];
        $ts->ts_type = $parameters['TypeofTraining'];
        $ts->ts_department = $parameters['Department'];
        $ts->ts_start = $parameters['Start'];
        $ts->ts_end = $parameters['End'];
        $ts->ts_cost = $parameters['Cost'];
        $ts->ts_created_by = 2;//logged in user
        $ts->ts_status = 4;//not submitted //2 submitted/pending 3//canceled //4 approved
        $year  = new Year();
        $yr = $year->createYear(date('Y'));
        $ts->ts_year = $yr->id;//date('Y');//current year
        $ts->save();
        return true;
    }

}
