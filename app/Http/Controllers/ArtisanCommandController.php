<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArtisanCommandController extends Controller
{
    /**
     * Run Laravel optimize command via API.
     */
    public function optimize(Request $request)
    {
        Artisan::call('optimize');

        return response()->json([
            'message' => 'Optimize command executed successfully.',
        ], 200);
    }

    public function migrate(Request $request)
    {
        try {
            // Run the migrate command
            Artisan::call('migrate', [
                '--force' => true, // Important for production environments
            ]);

            return response()->json([
                'message' => 'Migrate command executed successfully.',
                'output' => Artisan::output(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Migrate command failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function clearAll(Request $request)
    {
        try {
            // Run artisan clear commands
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('optimize:clear');

            return response()->json([
                'message' => 'All clear commands executed successfully.',
                'output' => [
                    'config' => Artisan::output(),
                    'cache' => Artisan::output(),
                    'optimize' => Artisan::output(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Clear command failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
