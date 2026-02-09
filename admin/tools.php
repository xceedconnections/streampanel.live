<?php
/**
 * Admin Panel - Tools Landing Page
 */
$page_title = "Tools";
?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-tools mr-2 text-netflix-red"></i>Admin Tools
    </h1>
    
    <p class="text-gray-400 mb-6">Select a tool from the menu above or use the links below:</p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Match & Replace Tool -->
        <a href="?tab=match-replace" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-netflix-red">
            <div class="flex items-center mb-4">
                <div class="bg-netflix-red bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-exchange-alt text-netflix-red text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Match & Replace</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Upload an Excel/CSV file to match TV channels by name and update their category and country information.
            </p>
        </a>
        
        <!-- Import SQL Tool -->
        <a href="?tab=import-sql" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-netflix-red">
            <div class="flex items-center mb-4">
                <div class="bg-netflix-red bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-database text-netflix-red text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Import SQL</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Import TV channels from a SQL file. Updates existing channels and adds new ones without deleting any existing channels.
            </p>
        </a>
        
        <!-- Remove Bad M3U8 Sources Tool -->
        <a href="?tab=remove-bad-m3u8-sources" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-green-600">
            <div class="flex items-center mb-4">
                <div class="bg-green-600 bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-link text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Remove Bad M3U8 Sources</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Check and remove non-working M3U8/HLS streaming sources. Channels are preserved, only bad sources are removed. Properly checks HTTP, CORS, and content.
            </p>
        </a>
        
        <!-- Remove Bad DASH Sources Tool -->
        <a href="?tab=remove-bad-dash-sources" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-green-600">
            <div class="flex items-center mb-4">
                <div class="bg-green-600 bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-link text-green-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Remove Bad DASH Sources</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Check and remove non-working DASH/MPD streaming sources. Channels are preserved, only bad sources are removed. Properly checks HTTP, CORS, and content.
            </p>
        </a>
        
        <!-- Delete M3U8 Channels Tool -->
        <a href="?tab=delete-m3u8-channels" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-red-600">
            <div class="flex items-center mb-4">
                <div class="bg-red-600 bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-trash-alt text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Delete M3U8 Channels</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Delete all TV channels that have only m3u8 links. Channels with YouTube, iframe, or other sources are preserved.
            </p>
        </a>
        
        <!-- Delete DASH Channels Tool -->
        <a href="?tab=delete-dash-channels" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-red-600">
            <div class="flex items-center mb-4">
                <div class="bg-red-600 bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-trash-alt text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Delete DASH Channels</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Delete all TV channels that have only DASH/MPD streaming links. Channels with YouTube, iframe, m3u8, or other sources are preserved.
            </p>
        </a>
        
        <!-- Delete No Source Channels Tool -->
        <a href="?tab=delete-no-source-channels" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-red-600">
            <div class="flex items-center mb-4">
                <div class="bg-red-600 bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-ban text-red-600 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Delete No Source Channels</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Delete all TV channels that have no sources configured. Channels with any valid sources will be preserved.
            </p>
        </a>

        <!-- Remove HTTP-only HLS/DASH Stream Links -->
        <a href="?tab=remove-http-stream-links" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-yellow-500">
            <div class="flex items-center mb-4">
                <div class="bg-yellow-500 bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-unlock-alt text-yellow-500 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Remove HTTP Stream Links</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Find and remove all HLS/DASH stream links whose URL starts with <code>http://</code> and source type is <code>hls</code>, <code>m3u8</code>, or <code>dash</code>. Channels are preserved; only those links are removed.
            </p>
        </a>

        <!-- Search & Check Streams by Category -->
        <a href="?tab=search-check-streams" class="bg-gray-800 hover:bg-gray-700 rounded-lg p-6 transition-all border border-gray-700 hover:border-blue-500">
            <div class="flex items-center mb-4">
                <div class="bg-blue-500 bg-opacity-20 p-3 rounded-lg mr-4">
                    <i class="fas fa-search text-blue-500 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold">Search & Check Streams</h3>
            </div>
            <p class="text-gray-400 text-sm">
                Filter channels by category and check their HLS (M3U8) and DASH (MPD) stream links. View a progress bar while checking and remove dead links from the selected group.
            </p>
        </a>
    </div>
</div>
