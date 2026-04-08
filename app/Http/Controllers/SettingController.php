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
   // Add this to your update method in SettingController.php temporarily for debugging
public function update(Request $request)
{
    
    
    $validator = Validator::make($request->all(), [
        'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        'favicon' => 'nullable|image|mimes:ico,png|max:1024',
        'landerpage_domain' => 'nullable|string|max:255',
        'default_sale_hide' => 'nullable|integer|min:0|max:10',
        'default_master_password' => 'nullable|string|min:6',
        'default_payment_mail' => 'nullable|email|max:255',
        'default_affiliate_commission_1' => 'nullable|integer|min:0|max:100',
        'default_affiliate_commission_2' => 'nullable|integer|min:0|max:100',
        'default_affiliate_commission_3' => 'nullable|integer|min:0|max:100',
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
    if ($request->has('landerpage_domain')) {
        $data['landerpage_domain'] = $request->landerpage_domain;
    }

    if ($request->has('default_sale_hide')) {
        $data['default_sale_hide'] = (int) $request->default_sale_hide;
    }
      if ($request->has('default_affiliate_commission_1')) {
            $data['default_affiliate_commission_1'] = (int) $request->default_affiliate_commission_1;
        }
        
        if ($request->has('default_affiliate_commission_2')) {
            $data['default_affiliate_commission_2'] = (int) $request->default_affiliate_commission_2;
        }
        
        if ($request->has('default_affiliate_commission_3')) {
            $data['default_affiliate_commission_3'] = (int) $request->default_affiliate_commission_3;
        }

    if ($request->has('default_payment_mail')) {
        $data['default_payment_mail'] = $request->default_payment_mail;
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
    
   
    // Don't expose master password in response
    $settings->makeHidden(['default_master_password']);
    
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
                'data' => $settings->makeHidden(['default_master_password'])
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
                'data' => $settings->makeHidden(['default_master_password'])
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No favicon found to remove'
        ], 404);
    }
}