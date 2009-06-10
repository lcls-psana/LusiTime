<?php

/*
 * The class representing time in Web applications. It has
 * two data members representing the number of seconds since UNIX
 * epoch (GMT timezone) and the number of nanoseconds. The number
 * of nanoseconds is in the range of 0..999999999.
 */
class LusiTime {

    /* Data members
    */
    public $sec;
    public $nsec;

    /* Factory method for getting the current time
     */
    public static function now() {
        return new LusiTime( mktime()); }

    /* Factory method from an input string to be parsed into an object
     * of the class.
     *
     * Input formats:
     *
     *   2009-05-19 17:59:49-07
     *   2009-05-19 17:59:49-07:00
     *   2009-05-19 17:59:49-0700
     *   2009-05-19 12:18:49.123456789-0700
     *
     * The last example illustrates how to specify nanoseconds. The number
     * of nanoseconds (if not empty) must be in the range of 0..999999999.
     *
     * The method will return an object of the class or it will
     * return null in case of any error.
     */
    public static function parse($str) {

        $expr = '/(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})(\.(\d{1,9}))?(([-+])(\d{2}):?(\d{2})?)/';
        if( !preg_match( $expr, $str, $matches2 ))
            return null;

        $sign = $matches2[10];
        $tzone_hours = $matches2[11];
        $tzone_minutes = isset( $matches2[12] ) ? $matches2[12] : 0;
        $shift = ($sign=='-' ? +1 : -1)*(3600*$tzone_hours + 60*$tzone_minutes);

        $local_time = strtotime( $str );
        if( !$local_time )
            return null;

        $gmt_time = ($local_time+$shift);

        $nsec = isset( $matches2[8] ) ? $matches2[8] : 0;

        return new LusiTime($gmt_time,$nsec);
    }

    /* Constructor
     */
    public function __construct($sec, $nsec=0) {
        if( $nsec < 0 or $nsec > 999999999 )
            throw new LusiTimeException(
                __METHOD__, "the number of nanoseconds ".$nsec." isn't in allowed range" );

        $this->sec = $sec;
        $this->nsec = $nsec;
    }

    /* Return a human-readable ISO representation
     * of date and time.
     */
    public function __toString() {
        return gmdate("Y-m-d h:i:s", $this->sec).sprintf(".%09u", $this->nsec)."-0000"; }

    /* Unlike the previous method this one would return a short (no
     * nanoseconds and time-zone) representation (ISO) of a human-readable
     * date and time.
     */
    public function toStringShort() {
        return gmdate("Y-m-d h:i:s", $this->sec); }

    /* Convert the tuple into a packed representation of a 64-bit
     * number. These numbers are meant to be stored in a database.
     *
     * NOTE: due to extended range of returned values which is not
     * nativelly supported by PHP the result is returned as a string.
     */
    public function to64() {
        return sprintf("%010d%09d", $this->sec, $this->nsec); }

    /* Produce a packed timestamp from an input value regardless whether
     * it's an object of the current class or it's already a packed
     * representation (64-bit number).
     */
    public static function to64from( $time ) {
        if(is_object($time)) return $time->to64();
        return $time;
    }

    /* Create an object of the current class out of a packed 64-bit
     * numeric representation.
     *
     * NOTE: the "numeric" representation is actually a string.
     */
    public static function from64( $packed ) {
        $sec = 0;
        $nsec = 0;
        $len = strlen( $packed );
        if( $len <= 9 ) {
            if( 1 != sscanf( $packed, "%ud", $nsec ))
                throw new LusiTimeException(
                    __METHOD__, "failed to translate nanoseconds" );
        } else {
            if( 1 != sscanf( substr( $packed, 0, $len - 9), "%ud", $sec ))
                throw new LusiTimeException(
                    __METHOD__, "failed to translate seconds" );

            if( 1 != sscanf( substr( $packed, -9 ), "%ud", $nsec ))
                throw new LusiTimeException(
                    __METHOD__, "failed to translate nanoseconds" );
        }
        return new LusiTime( $sec, $nsec );
    }

    /* Check if the input timestamp falls into the specified interval.
     * The method will return the following values:
     * 
     *    < 0  - the timestamp falls before the begin time of the interval
     *    = 0  - the timestamp is within the interval
     *    > 0  - the timestamp is at or beyon the end time of the interval
     * 
     * The method will throw an exception if either of the timestamp
     * is not valid, or if the end time of the interval is before its begin time.
     * The only exception is the end time, which is allowed to be the null
     * object for open-ended intervals.
     */
    public static function in_interval( $at, $begin, $end ) {

        if( is_null( $at ) || is_null( $begin ))
            throw new LusiTimeException(
                __METHOD__, "timestamps can't be null" );

        $at_obj    = (is_object( $at )    ? $at    : LusiTime::from64( $at ));
        $begin_obj = (is_object( $begin ) ? $begin : LusiTime::from64( $begin ));
        $end_obj = $end;

        if( !is_null( $end ) AND !is_object( $end )) $end_obj = LusiTime::from64( $end );
        if( $at_obj->less( $begin_obj )) return -1;
        if( is_null( $end_obj ) OR $at_obj->less( $end_obj )) return 0;
        return 1;
    }

    /* Compare the current object with the one given in the parameter.
     * Return TRUE if the current object is STRICTLY less than the given one.
     */
    public function less( $rhs ) {
        return ( $this->sec < $rhs->sec ) ||
               (( $this->sec == $rhs->sec ) && ($this->nsec < $rhs->nsec)); }

    public function greaterOrEqual( $rhs ) {
        return $rhs->less( $this ); }

    /* Compare the current object with the one given in the parameter.
     * Return TRUE if the current object is equal to the given one.
     */
    public function equal( $rhs ) {
        return ( $this->sec == $rhs->sec ) && ($this->nsec == $rhs->nsec); }
}
/*

echo "here follows a simple unit test for the class.\n";

$t64 = "1242812928000000000";
$r = LusiTime::from64($t64);
//$r = LusiTime::now();

print("<br>input:       ".$t64);
print("<br>result:      ".$r);
print("<br>result.sec:  ".$r->sec);
print("<br>result.nsec: ".$r->nsec);

$t1 = new LusiTime(1);
$t2 = new LusiTime(2);
$t3 = new LusiTime(3);

print( "<br>1 in [2,3)    : ".LusiTime::in_interval($t1, $t2, $t3)."\n" );
print( "<br>2 in [2,3)    : ".LusiTime::in_interval($t2, $t2, $t3)."\n" );
print( "<br>3 in [2,3)    : ".LusiTime::in_interval($t3, $t2, $t3)."\n" );
print( "<br>3 in [2,null) : ".LusiTime::in_interval($t3, $t2, null)."\n" );

$t_1_2 = new LusiTime(1,2);
$t_1_3 = new LusiTime(1,3);
$t_2_2 = new LusiTime(2,2);

print( "<br>1.2 <  1.3 : ".$t_1_2->less($t_1_3)."\n" );
print( "<br>!(1.3 <  1.2) : ".!$t_1_3->less($t_1_2)."\n" );
print( "<br>1.2 <  2.2 : ".$t_1_2->less($t_2_2)."\n" );
print( "<br>1.2 == 1.2 : ".$t_1_2->equal($t_1_2)."\n" );

$packed = '099999999';
print( "  Packed:     ".$packed."\n" );
print( "  Translated: ".LusiTime::from64( $packed )."\n" );

$str = "2009-05-19 17:59:49.123-0700";

$lt = LusiTime::parse( $str) ;

print( "  Input time:           ".$str."\n" );
print( "  LusiTime::parse(): ".$lt->__toString()."\n" );
print( "  converted to 64-bit:  ".$lt->to64()."\n" );

?>
*/