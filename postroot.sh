#!/bin/sh
# Will be executed as user "root".

if [ ! -e $5/bin/plugins/sonos4lox/piper/piper ]; then
	if [ -e $LBSCONFIG/is_raspberry.cfg ]; then
		echo "<INFO> The hardware architecture is RaspBerry"
		wget -P $5/bin/plugins/sonos4lox https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_aarch64.tar.gz
		cd $5/bin/plugins/sonos4lox
		tar -xvzf piper_linux_aarch64.tar.gz
		rm piper_linux_aarch64.tar.gz
	fi

	if [ -e $LBSCONFIG/is_x86.cfg ]; then
		echo "<INFO> The hardware architecture is x86"
		wget -P $5/bin/plugins/sonos4lox https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_x86_64.tar.gz
		cd $5/bin/plugins/sonos4lox
		tar -xvzf piper_linux_x86_64.tar.gz
		rm piper_linux_x86_64.tar.gz
	fi

	if [ -e $LBSCONFIG/is_x64.cfg ]; then
		echo "<INFO> The hardware architecture is x64"
		if [ "$INST" != true ]; then
			wget -P $5/bin/plugins/sonos4lox https://github.com/rhasspy/piper/releases/download/2023.11.14-2/piper_linux_aarch64.tar.gz
			cd $5/bin/plugins/sonos4lox
			tar -xvzf piper_linux_aarch64.tar.gz
			rm piper_linux_aarch64.tar.gz
		else
			echo "<INFO> Piper TTS has already been installed"
		fi
	fi
else
	echo "<INFO> Piper TTS is already installed, nothing to do"
fi

chmod +x $5/bin/plugins/sonos4lox/piper/piper
export PATH=$5/bin/plugins/sonos4lox/piper:$PATH

piper='/usr/bin/piper'
if [ -L $piper ]; then
	echo "<INFO> Symlink 'piper' is already available in /usr/bin"
else
	ln -s $5/bin/plugins/sonos4lox/piper/piper /usr/bin/piper
	echo "<INFO> Symlink 'piper' has been created in /usr/bin"
fi
exit 0