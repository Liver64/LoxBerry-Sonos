#!/bin/sh 
########################################################################
# file:         cpan.sh 
# desc:         Use this simple shell script to 
#               download and install PERL Modules 
#               via CPAN. 
# 
# author:       Sean O'Donnell http://www.seanodonnell.com/code/?id=50
# 
########################################################################

function print_help  { 
        echo "Usage: $0 [module]" 
} 

function install_module  { 
        echo "perl -MCPAN -e 'install $1'" 
        perl -MCPAN -e 'install $1' 
} 

if [ $1 ]; then 
        install_module $1 
else 
        print_help 
fi 

exit 0 