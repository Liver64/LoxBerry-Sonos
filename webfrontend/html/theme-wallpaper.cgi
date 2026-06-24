#!/usr/bin/perl -w
# =============================================================================
# Sonos4Lox - Liquid Glass wallpaper delivery
# Version: V01.0 2026-06-22
# =============================================================================
# Serves the custom Liquid Glass wallpaper from the plugin data directory.
# Falls back to the bundled default wallpaper if no custom file exists.
# =============================================================================

use LoxBerry::System;
use CGI::Carp qw(fatalsToBrowser);
use strict;
use warnings;
use utf8;

my $context = 'theme-wallpaper.cgi';

sub find_wallpaper_file
{
    my $data_dir = $lbpdatadir . '/themes';
    my @custom = map { "$data_dir/liquid-glass-background.$_" } qw(png jpg jpeg webp);

    for my $file (@custom) {
        return $file if -r $file;
    }

    my $default = $lbphtmldir . '/LayoutUI/themes/theme-liquid-glass-background.png';
    return $default if -r $default;

    return '';
}

sub mime_for_file
{
    my ($file) = @_;
    return 'image/jpeg' if $file =~ /\.jpe?g$/i;
    return 'image/webp' if $file =~ /\.webp$/i;
    return 'image/png';
}

my $file = find_wallpaper_file();

if (!$file) {
    print "Status: 404 Not Found\r\n";
    print "Content-Type: text/plain; charset=utf-8\r\n";
    print "Cache-Control: no-store, no-cache, must-revalidate\r\n\r\n";
    print "$context: wallpaper file not found\n";
    exit;
}

open(my $fh, '<:raw', $file) or do {
    print "Status: 403 Forbidden\r\n";
    print "Content-Type: text/plain; charset=utf-8\r\n";
    print "Cache-Control: no-store, no-cache, must-revalidate\r\n\r\n";
    print "$context: wallpaper file is not readable\n";
    exit;
};

my @stat = stat($file);
my $size = $stat[7] || 0;
my $mtime = $stat[9] || time();
my $mime = mime_for_file($file);

print "Content-Type: $mime\r\n";
print "Content-Length: $size\r\n" if $size > 0;
print "Cache-Control: public, max-age=86400\r\n";
print "Last-Modified: " . scalar(gmtime($mtime)) . " GMT\r\n";
print "\r\n";

binmode STDOUT;
while (read($fh, my $buffer, 8192)) {
    print $buffer;
}
close($fh);
exit;
