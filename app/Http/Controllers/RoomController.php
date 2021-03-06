<?php

namespace App\Http\Controllers;

use App\Model\Room;
use Illuminate\Http\Request;

use App\Http\Requests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class RoomController extends Controller {

    //跳转首页
    public function index(){
        return Redirect::to(route('index'));
    }

    //直播间首页
    public function show($roomId){
        $room = Room::find($roomId);
        if ($room){
            $room->user;
            return view('FrontEnd.room')->with('room', $room);
        }
        return abort(404);
    }

    //申请直播间
    public function apply(){
        $room = Room::where('user_id', '=', Auth::id())->first();
        if ($room) {

            return [
                'status' => 403,
                'info'   => '你已申请直播间'
            ];
        }
        $data = [
            'user_id' => Auth::id()
        ];
        Room::create($data);
        return [
            'status' => 200,
            'info'   => '申请成功, 待审核'
        ];
    }

    //获取直播码
    public function update($roomId){
        $room = Room::where('id', '=', $roomId)->where('user_id', '=', Auth::id())->first();
        if(!$room){
            return ['status' => 403, 'info' => '直播间号错误/未开通直播'];
        }
        switch ($room['status']){
            case 0:
                return ['status' => 403, 'info' => '黑名单直播间'];
            case 1:
                break;
            case 2:
                return ['status' => 403, 'info' => '正在直播中'];
            case 3:
                return ['status' => 403, 'info' => '直播间申请待审核'];
            default:
                return ['status' => 500, 'info' => '未知错误'];
        }
        $token = str_random(64);
        Room::where('id', '=', $roomId)->where('user_id', '=', Auth::id())->update([
            'living_token' => $token,
            'status' => 2
        ]);
        return [
            'status' => 200,
            'info' => '成功',
            'data' => 'live_room_id_'.$roomId.'?room_id='.$roomId.'&user_id='.Auth::id().'&token='.$token
        ];
    }

    //关闭直播间
    public function destroy($roomId){
        if(Room::where('id', '=', $roomId)->where('status', '=', 2)->where('user_id', '=', Auth::id())->update([
            'status' => 1
        ])){
            return ['status' => 200, 'info' => '成功'];
        }
        return ['status' => 500, 'info' => '刷新下试试'];
    }

    //检查是否有权限串流
    public function publish(Request $request){
        $room = Room::find($request['room_id']);
        if ($room){
            $room->user;
            if($room['living_token'] == $request['token'] && $room['user_id'] == $request['user_id'] && $room['status'] == 1) {
                if (time() - strtotime($room['updated_at']) < 1800){
                    Room::where('id', '=', $request['room_id'])->update(['status'=>2]);
                } else {
                    return abort(403);
                }
                return ['status' => 200];
            }
        }
        return abort(403);
    }

    //串流结束更改状态
    public function publishDone(Request $request){
        $room = Room::find($request['room_id']);
        if ($room){
            $room->user;
            if($room['living_token'] == $request['token'] && $room['user_id'] == $request['user_id']) {
                Room::where('id', '=', $request['room_id'])->update(['status'=>1]);
                return ['status' => 200];
            }
        }
        return abort(403);
    }
}
