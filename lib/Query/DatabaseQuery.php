<?php
 
namespace lib\Query;

class DatabaseQuery
{

    public function getDatabaseDic()
    {
        return "SELECT [RutaEmpresa], [NombreEmpresa] FROM [nomGenerales].[dbo].[NOM10000];";
    }

    public function getDatabaseWorkerDic()
    {
        return "SELECT idempleado, [numerosegurosocial] As AfiliacionNSS ,[codigoempleado] As CodigoEmpleado ,[nombrelargo] As NombreTrabajador ,CURPI, Cast(Replace(Convert(varchar(10),fechanacimiento, 12), '', '') as varchar(10)) as fechanacimiento, CURPF ,RFC AS PrimeraParteRFC,Cast(Replace(Convert(varchar(10),fechanacimiento, 12), '', '') as varchar(10)) as fechanacimiento ,Homoclave as TerceraParteRFC ,[zonasalario] as Clave FROM [nom10001];";
    }

    public function getDatabaseWorkerAusc ()
    {
        return "SELECT idempleado, SUM(valor) as ausc FROM nom10009 group by idempleado;";
    }

    public function getDatabaseWorkerIncp ()
    {
        return "SELECT [idempleado], [diasautorizados], [fechainicio] FROM [nom10018]";
    }

    public function getRegPatDic ()
    {
        return "SELECT cidregistropatronal,cregistroimss FROM NOM10035 ORDER BY cidregistropatronal";
    }
    
}