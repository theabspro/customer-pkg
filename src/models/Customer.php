<?php

namespace Abs\CustomerPkg;

use Abs\BasicPkg\Address;
use Abs\HelperPkg\Traits\SeederTrait;
use App\Company;
use App\Config;
use Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model {
	use SeederTrait;
	use SoftDeletes;
	protected $table = 'customers';
	public $timestamps = true;
	protected $fillable = [
		'code',
		'name',
		'cust_group',
		'dimension',
		'mobile_no',
		'email',
		'company_id',
		'address',
	];

	public static function createFromObject($record_data) {

		$errors = [];
		$company = Company::where('code', $record_data->company)->first();
		if (!$company) {
			dump('Invalid Company : ' . $record_data->company);
			return;
		}

		$admin = $company->admin();
		if (!$admin) {
			dump('Default Admin user not found');
			return;
		}

		$type = Config::where('name', $record_data->type)->where('config_type_id', 89)->first();
		if (!$type) {
			$errors[] = 'Invalid Tax Type : ' . $record_data->type;
		}

		if (count($errors) > 0) {
			dump($errors);
			return;
		}

		$record = self::firstOrNew([
			'company_id' => $company->id,
			'name' => $record_data->tax_name,
		]);
		$record->type_id = $type->id;
		$record->created_by_id = $admin->id;
		$record->save();
		return $record;
	}

	public function city() {
		return $this->belongsTo('App\City', 'city_id');
	}

	public function state() {
		return $this->belongsTo('App\State', 'state_id');
	}
	public function getFormattedAddress() {
		$customer = $this;
		if (!$customer->address) {
			return 'N/A';
		}
		$formatted_address = '';
		$formatted_address .= !empty($customer->address) ? $customer->address : '';
		$formatted_address .= $customer->city ? ', ' . $customer->city : '';
		$formatted_address .= $customer->zipcode ? ', ' . $customer->zipcode : '';
		return $formatted_address;

	}

	public static function searchCustomer($r) {
		$key = $r->key;
		$list = self::where('company_id', Auth::user()->company_id)
			->select(
				'id',
				'name',
				'code'
			)
			->where(function ($q) use ($key) {
				//ISSUE : full pattern search should be avoided
				$q->where('name', 'like', $key . '%')
					->orWhere('code', 'like', $key . '%')
					->orWhere('mobile_no', 'like', $key . '%')
				;
				// $q->where('name', 'like', '%' . $key . '%')
				// 	->orWhere('code', 'like', '%' . $key . '%')
				// 	->orWhere('mobile_no', 'like', '%' . $key . '%')
				// ;

			})
			->get();
		return response()->json($list);
	}

	public static function getCustomer($request) {
		$customer = self::find($request->id);

		//ISSUE : CRAZY
		// if ($request->value == "fromAcc") {
		// 	$customer = self::where('id', $request->customer_id)->first();
		// 	$transfer_type = "FromAccount";
		// } else {
		// 	$customer = self::where('id', $request->customer_id)->first();
		// 	$transfer_type = "ToAccount";
		// }

		if (!$customer) {
			return response()->json(['success' => false, 'error' => 'Customer not found']);
		}
		// $customer->formatted_address = $customer->primaryAddress ? $customer->primaryAddress->formatted_address : 'NA';
		return response()->json([
			'success' => true,
			// ISSUE : CRAZY
			// 'transfer_type' => $transfer_type,
			'customer' => $customer,
		]);
	}

	public function addresses() {
		return $this->hasMany('Abs\BasicPkg\Address', 'entity_id')->where('address_of_id', 24);
	}

	public function primaryAddress() {
		return $this->hasOne('Abs\BasicPkg\Address', 'entity_id')->where('address_of_id', 24)->where('address_type_id', 40)
		//->first()
		;
	}

	public function customerDetails() {
		return $this->hasOne('App\CustomerDetails');
	}

}
