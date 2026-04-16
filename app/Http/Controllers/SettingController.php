<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::getSettings();
        
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png|max:1024',
            'landerpage_domain' => 'nullable|string|max:255',
            'player_page_domain' => 'nullable|string|max:255',
            
            // Manager Information
            'manager_name' => 'nullable|string|max:255',
            'manager_email' => 'nullable|email|max:255',
            'manager_telegram' => 'nullable|string|max:255',
            'manager_microsoft' => 'nullable|string|max:255',
            
            'default_sale_hide' => 'nullable|integer|min:0|max:10',
            'default_master_password' => 'nullable|string|min:6',
            'default_payment_mail' => 'nullable|email|max:255',
            'default_affiliate_commission_1' => 'nullable|integer|min:0|max:100',
            'default_affiliate_commission_2' => 'nullable|integer|min:0|max:100',
            'default_affiliate_commission_3' => 'nullable|integer|min:0|max:100',
            'is_paypal_active' => 'nullable|boolean',
            'is_payoneer_active' => 'nullable|boolean',
            'is_bank_transfer_active' => 'nullable|boolean',
            'is_binance_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Get current settings to handle image deletion
        $currentSettings = Setting::getSettings();
        $data = [];

        // Handle text fields
        $textFields = [
            'landerpage_domain',
            'player_page_domain',
            'manager_name',
            'manager_email',
            'manager_telegram',
            'manager_microsoft',
            'default_payment_mail',
        ];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->$field;
            }
        }

        // Handle integer fields
        $integerFields = [
            'default_sale_hide',
            'default_affiliate_commission_1',
            'default_affiliate_commission_2',
            'default_affiliate_commission_3',
        ];

        foreach ($integerFields as $field) {
            if ($request->has($field)) {
                $data[$field] = (int) $request->$field;
            }
        }

        // Handle boolean fields
        $booleanFields = [
            'is_paypal_active',
            'is_payoneer_active',
            'is_bank_transfer_active',
            'is_binance_active',
        ];

        foreach ($booleanFields as $field) {
            if ($request->has($field)) {
                $data[$field] = filter_var($request->$field, FILTER_VALIDATE_BOOLEAN);
            }
        }
        
        // Handle password - only update if provided
        if ($request->has('default_master_password') && !empty($request->default_master_password)) {
            $data['default_master_password'] = $request->default_master_password;
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($currentSettings && $currentSettings->logo) {
                Storage::disk('public')->delete($currentSettings->logo);
            }

            $logo = $request->file('logo');
            $logoName = time() . '_logo.' . $logo->getClientOriginalExtension();
            $logoPath = $logo->storeAs('settings', $logoName, 'public');
            $data['logo'] = $logoPath;
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            // Delete old favicon if exists
            if ($currentSettings && $currentSettings->favicon) {
                Storage::disk('public')->delete($currentSettings->favicon);
            }

            $favicon = $request->file('favicon');
            $faviconName = time() . '_favicon.' . $favicon->getClientOriginalExtension();
            $faviconPath = $favicon->storeAs('settings', $faviconName, 'public');
            $data['favicon'] = $faviconPath;
        }
        
        // Update or create settings
        $settings = Setting::updateSettings($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $settings
        ]);
    }

    public function removeLogo()
    {
        $settings = Setting::getSettings();
        
        if ($settings && $settings->logo) {
            Storage::disk('public')->delete($settings->logo);
            $settings->update(['logo' => null]);
            
            return response()->json([
                'success' => true,
                'message' => 'Logo removed successfully',
                'data' => $settings
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No logo found to remove'
        ], 404);
    }

    /**
     * Remove favicon
     */
    public function removeFavicon()
    {
        $settings = Setting::getSettings();
        
        if ($settings && $settings->favicon) {
            Storage::disk('public')->delete($settings->favicon);
            $settings->update(['favicon' => null]);
            
            return response()->json([
                'success' => true,
                'message' => 'Favicon removed successfully',
                'data' => $settings
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No favicon found to remove'
        ], 404);
    }
}